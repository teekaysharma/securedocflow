<?php
/*
 * Copyright (C) 2000-2025. Stephen Lawrence
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

// In-browser preview for non-PDF document formats (Word, Excel, PowerPoint,
// OpenDocument, RTF), via LibreOffice headless conversion to PDF. Converted
// PDFs are cached on disk keyed by file id + doc_version + doc_revision, so
// a new check-in naturally invalidates the cache without needing to track
// invalidation explicitly.

if (!defined('PreviewGenerator_class')) {
    define('PreviewGenerator_class', 'true', false);

    class PreviewGenerator
    {
        // Extensions LibreOffice can convert to PDF that OpenDocMan doesn't
        // already serve natively (PDF, images).
        public static $convertibleExtensions = array(
            'doc', 'docx', 'odt', 'rtf',
            'xls', 'xlsx', 'ods', 'csv',
            'ppt', 'pptx', 'odp'
        );

        // Conservative cap on a single conversion — LibreOffice headless can
        // hang on a corrupt or hostile input file, and this runs inline in a
        // web request, so it must not be allowed to run indefinitely.
        const CONVERSION_TIMEOUT_SECONDS = 45;

        /**
         * @param string $extension (no leading dot, any case)
         * @return bool
         */
        public static function isConvertible($extension)
        {
            return in_array(strtolower($extension), self::$convertibleExtensions);
        }

        /**
         * Get (generating and caching if needed) the path to a PDF preview
         * of $sourceFilePath. Returns null if conversion isn't possible or
         * fails — callers should fall back to the original download/view
         * behavior in that case, not treat it as a hard error.
         * @param string $sourceFilePath absolute path to the original file on disk
         * @param int $fileId
         * @param int $docVersion
         * @param int $docRevision
         * @return string|null absolute path to the cached PDF, or null
         */
        public static function getPreviewPath($sourceFilePath, $fileId, $docVersion, $docRevision)
        {
            if (!is_file($sourceFilePath)) {
                return null;
            }

            $cacheDir = self::getCacheDir();
            if ($cacheDir === null) {
                return null;
            }

            self::maybeEvictStaleCache($cacheDir);

            $cachedPdf = $cacheDir . $fileId . '_' . (int) $docVersion . '_' . (int) $docRevision . '.pdf';
            if (is_file($cachedPdf) && filesize($cachedPdf) > 0) {
                return $cachedPdf;
            }

            return self::convert($sourceFilePath, $cachedPdf);
        }

        // How often the eviction sweep runs at most, regardless of preview
        // traffic — a cheap file mtime check on every request, but the actual
        // directory scan + DB lookup only every EVICTION_INTERVAL_SECONDS.
        const EVICTION_INTERVAL_SECONDS = 86400; // once a day

        /**
         * Delete cached previews that are no longer reachable by any current
         * document: either the source document was deleted entirely (its id
         * no longer exists in odm_data), or it's been superseded by a newer
         * check-in (the cache key's doc_version/doc_revision no longer
         * matches the document's current one — the old cache entry becomes
         * unreachable the moment a new version is checked in, since
         * getPreviewPath() only ever looks up the *current* version/revision
         * key, but nothing removed the stale file from disk until now).
         * Rate-limited via a marker file so this only actually scans once
         * per EVICTION_INTERVAL_SECONDS, not on every preview request.
         * @param string $cacheDir
         */
        private static function maybeEvictStaleCache($cacheDir)
        {
            $marker = $cacheDir . '.last_eviction';
            if (is_file($marker) && (time() - filemtime($marker)) < self::EVICTION_INTERVAL_SECONDS) {
                return;
            }
            // Touch the marker first so concurrent requests arriving while
            // this sweep is running don't all kick off their own sweep too.
            @touch($marker);

            $pdo = isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : null;
            if ($pdo === null) {
                return;
            }

            $current = array();
            $stmt = $pdo->query("SELECT id, doc_version, doc_revision FROM {$GLOBALS['CONFIG']['db_prefix']}data");
            foreach ($stmt->fetchAll() as $row) {
                $current[(int) $row['id']] = (int) $row['doc_version'] . '_' . (int) $row['doc_revision'];
            }

            $deletedCount = 0;
            foreach (glob($cacheDir . '*.pdf') as $cachedFile) {
                $basename = pathinfo($cachedFile, PATHINFO_FILENAME);
                if (!preg_match('/^(\d+)_(\d+)_(\d+)$/', $basename, $m)) {
                    continue;
                }
                $fileId = (int) $m[1];
                $versionRevision = $m[2] . '_' . $m[3];

                $isOrphaned = !isset($current[$fileId]);
                $isSuperseded = !$isOrphaned && $current[$fileId] !== $versionRevision;
                if ($isOrphaned || $isSuperseded) {
                    if (@unlink($cachedFile)) {
                        $deletedCount++;
                    }
                }
            }

            if ($deletedCount > 0) {
                error_log('PreviewGenerator: eviction sweep removed ' . $deletedCount . ' stale cached preview(s)');
            }
        }

        /**
         * @return string|null the cache directory (with trailing slash), or
         * null if it doesn't exist and couldn't be created
         */
        private static function getCacheDir()
        {
            $dataDir = rtrim($GLOBALS['CONFIG']['dataDir'], '/\\');
            $cacheDir = dirname($dataDir) . DIRECTORY_SEPARATOR . 'preview_cache' . DIRECTORY_SEPARATOR;
            if (!is_dir($cacheDir)) {
                if (!@mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
                    error_log('PreviewGenerator: could not create cache directory ' . $cacheDir);
                    return null;
                }
            }
            return $cacheDir;
        }

        /**
         * Run LibreOffice headless conversion, with a timeout, into a
         * dedicated per-conversion temp directory (both for the output —
         * LibreOffice names the output file after the input, so it can't
         * write directly into the shared cache dir under a different name
         * — and for -env:UserInstallation, since concurrent soffice
         * processes sharing one profile directory can lock each other out).
         * @param string $sourceFilePath
         * @param string $destinationPdfPath
         * @return string|null
         */
        private static function convert($sourceFilePath, $destinationPdfPath)
        {
            $sofficePath = self::findSoffice();
            if ($sofficePath === null) {
                return null;
            }

            $workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'odm_preview_' . uniqid();
            $profileDir = $workDir . DIRECTORY_SEPARATOR . 'profile';
            if (!@mkdir($profileDir, 0755, true)) {
                error_log('PreviewGenerator: could not create working directory ' . $workDir);
                return null;
            }

            $command = array(
                $sofficePath,
                '--headless',
                '--norestore',
                '-env:UserInstallation=file:///' . str_replace('\\', '/', $profileDir),
                '--convert-to', 'pdf',
                '--outdir', $workDir,
                $sourceFilePath
            );

            $descriptorSpec = array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w')
            );

            $process = @proc_open($command, $descriptorSpec, $pipes, $workDir);
            if (!is_resource($process)) {
                error_log('PreviewGenerator: failed to start soffice process');
                self::cleanupDir($workDir);
                return null;
            }

            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $start = time();
            $status = proc_get_status($process);
            while ($status['running'] && (time() - $start) < self::CONVERSION_TIMEOUT_SECONDS) {
                usleep(200000); // 200ms poll interval
                $status = proc_get_status($process);
            }

            if ($status['running']) {
                // Timed out — kill it, this conversion is abandoned.
                proc_terminate($process, 9);
                error_log('PreviewGenerator: soffice conversion timed out after ' . self::CONVERSION_TIMEOUT_SECONDS . 's for ' . $sourceFilePath);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                self::cleanupDir($workDir);
                return null;
            }

            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            // LibreOffice names the output after the input file's basename.
            $expectedOutputName = pathinfo($sourceFilePath, PATHINFO_FILENAME) . '.pdf';
            $producedPdf = $workDir . DIRECTORY_SEPARATOR . $expectedOutputName;

            if (!is_file($producedPdf) || filesize($producedPdf) === 0) {
                error_log('PreviewGenerator: conversion produced no output for ' . $sourceFilePath);
                self::cleanupDir($workDir);
                return null;
            }

            // Strip document metadata (author, company, creation timestamps,
            // etc.) that LibreOffice carries straight through from the
            // source file's own properties — confirmed via a real test file
            // to include actual personally-identifying names. This matters
            // more than usual here: some of these documents are classified
            // Sensitive/Highly sensitive, and a preview that leaks who
            // authored it undermines the point of that classification if it
            // ever leaves the system. If qpdf is missing or fails, fall back
            // to the unstripped conversion rather than failing the whole
            // preview — but log it clearly, since this is a real
            // information-disclosure concern, not just a nicety.
            $finalSource = $producedPdf;
            $qpdfPath = self::findQpdf();
            if ($qpdfPath !== null) {
                $strippedPdf = $workDir . DIRECTORY_SEPARATOR . 'stripped.pdf';
                $qpdfCommand = array($qpdfPath, '--remove-info', '--remove-metadata', $producedPdf, $strippedPdf);
                $qpdfDescriptorSpec = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
                $qpdfProcess = @proc_open($qpdfCommand, $qpdfDescriptorSpec, $qpdfPipes);
                if (is_resource($qpdfProcess)) {
                    fclose($qpdfPipes[1]);
                    fclose($qpdfPipes[2]);
                    $qpdfExit = proc_close($qpdfProcess);
                    if ($qpdfExit === 0 && is_file($strippedPdf) && filesize($strippedPdf) > 0) {
                        $finalSource = $strippedPdf;
                    } else {
                        error_log('PreviewGenerator: qpdf metadata stripping failed (exit ' . $qpdfExit . ') for ' . $sourceFilePath . ' — serving unstripped preview');
                    }
                }
            } else {
                error_log('PreviewGenerator: qpdf not found — serving unstripped preview for ' . $sourceFilePath);
            }

            $moved = @rename($finalSource, $destinationPdfPath);
            self::cleanupDir($workDir);

            return $moved ? $destinationPdfPath : null;
        }

        /**
         * Locate qpdf.exe, used to strip document metadata from converted
         * previews. Absence is not fatal — see the caller.
         * @return string|null
         */
        private static function findQpdf()
        {
            $candidates = array(
                'C:\\Program Files\\PDF24\\qpdf\\bin\\qpdf.exe',
                '/usr/bin/qpdf',
            );
            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
            // winget installs qpdf into a version-numbered folder
            // ("qpdf 12.3.2") that changes on every update — glob for it
            // rather than hardcoding a version that will go stale.
            $versionedMatches = glob('C:\\Program Files\\qpdf *\\bin\\qpdf.exe');
            if (!empty($versionedMatches)) {
                return $versionedMatches[0];
            }
            return null;
        }

        /**
         * Locate soffice.exe (Windows) / soffice (Linux). Checked once per
         * request, not cached across requests — this is a low-frequency
         * operation (only runs when a preview cache miss happens) so the
         * repeated filesystem checks are not worth the complexity of a
         * cross-request cache.
         * @return string|null
         */
        private static function findSoffice()
        {
            $candidates = array(
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
                '/usr/bin/soffice',
                '/usr/bin/libreoffice',
            );
            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
            return null;
        }

        private static function cleanupDir($dir)
        {
            if (!is_dir($dir)) {
                return;
            }
            $items = scandir($dir);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    self::cleanupDir($path);
                } else {
                    @unlink($path);
                }
            }
            @rmdir($dir);
        }
    }
}
