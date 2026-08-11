

<script type="text/javascript" src="{$g_base_url}js/functions.js"></script>

<!-- file upload formu using ENCTYPE -->
<form id="addeditform" name="main" action="add" method="POST" enctype="multipart/form-data" onsubmit="return checksec();">
    {$csrf_token_field}
    <input type="hidden" id="db_prefix" value="{$db_prefix|escape:'html'}" />
<table border="0" cellspacing="5" cellpadding="5">
{assign var='i' value='0'}    
{foreach from=$t_name item=name name='loop1'}
    <input type="hidden" id="secondary{$i|escape:'html'}" name="secondary{$i|escape:'html'}" value="" /> <!-- CHM hidden and onsubmit added-->
    <input type="hidden" id="tablename{$i|escape:'html'}" name="tablename{$i|escape:'html'}" value="{$name|escape:'html'}" /> <!-- CHM hidden and onsubmit added-->
    {assign var='i' value=$i+1}
{/foreach}
    <input id="i_value" type="hidden" name="i_value" value="{$i|escape:'html'}" /> <!-- CHM hidden and onsubmit added-->
    <tr>
        <td>
            <a class="body" tabindex=1 href="help.html#Add_File_-_File_Location" onClick="return popup(this, 'Help')" style="text-decoration:none">{$g_lang_label_file_location}</a>
        </td>
        <td colspan=3>
            <input tabindex="0" name="file[]" type="file" multiple="multiple">
        </td>
    </tr>
    
{if $is_admin == true }
    <tr>

        <td>
            {$g_lang_editpage_assign_owner}
        </td>
        <td>
            <select name="file_owner">
            {foreach from=$avail_users item=user}
                <option value="{$user.id}" {$user.selected}>{$user.last_name|escape:'html'}, {$user.first_name|escape:'html'}</option>
            {/foreach}
            </select>
        </td>
    </tr>
    <tr>
        <td>
            {$g_lang_editpage_assign_department}
        </td>
        <td>
               
            <select name="file_department">
            {foreach from=$avail_depts item=dept}
                <option value="{$dept.id}" {$dept.selected}>{$dept.name|escape:'html'}</option>
            {/foreach}
            </select>
        </td>
    </tr>
{/if}    
    <tr>
        <td>
            <a class="body" href="help.html#Add_File_-_Category"  onClick="return popup(this, 'Help')" style="text-decoration:none">{$g_lang_category}</a>
        </td>
        <td colspan=3>
            <select tabindex=2 name="category" >
            {foreach from=$cats_array item=cat}
                <option value="{$cat.id}">{$cat.name|escape:'html'}</option>
            {/foreach}
            </select>
        </td>
    </tr>
    <tr>
        <td>
            Information Classification
        </td>
        <td colspan=3>
            <select tabindex=3 name="classification">
                <option value="Public">Public</option>
                <option value="Internal" selected>Internal</option>
                <option value="Limited">Limited</option>
                {if $can_set_high_classification}
                <option value="Sensitive">Sensitive</option>
                <option value="Highly sensitive">Highly sensitive</option>
                {/if}
            </select>
        </td>
    </tr>
    <tr>
        <td>
            Workflow Template
        </td>
        <td colspan=3>
            <select name="workflow_template_id">
                <option value="">-- No staged workflow (classic single-reviewer approval) --</option>
                {foreach from=$avail_workflow_templates item=wft}
                <option value="{$wft.id|escape}">{$wft.name|escape:'html'} ({$wft.stage_count|escape} stage{if $wft.stage_count ne 1}s{/if})</option>
                {/foreach}
            </select>
            &nbsp;Required for Sensitive (2+ stages) and Highly sensitive (3+ stages).
        </td>
    </tr>
    <!-- Set Department rights on the file -->
    <tr id="departmentSelect">
        <td>
            <a class="body" href="help.html#Add_File_-_Department" onClick="return popup(this, 'Help')" style="text-decoration:none">{$g_lang_addpage_permissions}</a>
        </td>
        <td colspan=3>
            <hr />
            {include file='../../views/common/_filePermissions.tpl'}
            <hr />
        </td>
    </tr>
    <tr>
        <td>
            <a class="body" href="help.html#Add_File_-_Description" onClick="return popup(this, 'Help')" style="text-decoration:none">{$g_lang_label_description}</a>
        </td>
        <td colspan="3"><input tabindex="5" type="Text" name="description" size="50"></td>
    </tr>

    <tr>
        <td>
            <a class="body" href="help.html#Add_File_-_Comment" onClick="return popup(this, 'Help')" style="text-decoration:none">{$g_lang_label_comment}</a>
        </td>
        <td colspan="3"><textarea tabindex="6" name="comment" rows="4" onchange="this.value=enforceLength(this.value, 255);"></textarea></td>
    </tr>