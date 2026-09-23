# User Guide

This is an end-user guide to SecureDocFlow's access-control and review features —
**Classification, Staged Approval, Access Requests, and Groups**. It's written for the person
uploading, reviewing, or requesting documents, not the person administering the deployment. For
installation and configuration, see the [README](README.md).

Every screen and button name below is taken directly from the application — nothing here is
paraphrased or aspirational. If your organization's menu wording differs slightly, your
administrator has customized the deployment; the underlying flow is still the same.

## Document Classification

Every document has a classification, set when it's uploaded and changeable later by an admin or
Department Head. It controls two things: who can see the document at all, and how many people
have to sign off before it's published.

**Tiers, from least to most restrictive:** Public, Internal (the default), Limited, Sensitive,
Highly sensitive.

**Setting it on upload:** the "Add Document" form has an **Information Classification** dropdown.
Only Admins and Department Heads can select **Sensitive** or **Highly sensitive** — if you don't
see those options, you don't have the rights to use them for this document, not that the feature
is broken.

**Changing it later:** the "Edit Document" screen shows the document's **Current Classification**
read-only, and — for users who can set high classification — a **Reclassify To** dropdown plus a
required **Reason for Reclassification** field. Reclassifying is logged; there's no silent way to
change a document's sensitivity.

**Why it matters to you:** Sensitive and Highly sensitive documents can't be published without
going through Staged Approval (see below) — the classification sets a *minimum* number of approval
stages the assigned workflow template must have.

## Staged Approval Workflow

Some documents need more than one person to sign off before they're visible to everyone —
Staged Approval is how that's enforced, distinct from the classic single-reviewer path lower-
classification documents use.

**If you're uploading a document that needs staged approval:** on the Add or Edit Document form,
pick a **Workflow Template** from the dropdown — each one lists how many stages it has (e.g. "Legal
+ Finance Sign-off (2 stages)"). A Sensitive document needs a template with at least 2 stages; a
Highly sensitive one needs at least 3. If you don't pick one and your document's classification
requires it, the form will tell you.

**Checking progress:** open the document's Details page. A row labeled **Approval Workflow** shows
where it stands — e.g. "Legal + Finance Sign-off — Stage 1 of 2." The document isn't visible to
regular users until every stage is approved.

**If you're an approver for a stage:** when it's your turn, the Details page for that document
shows **Approve** and **Reject** buttons. Approving moves the document to the next stage (or
publishes it, if that was the last stage); rejecting stops the workflow and notifies the uploader
by email that the document was rejected.

**Setting up a workflow template** (Admins/Department Heads): from the Admin menu's **Workflows**
section, **Add New Workflow Template** to name it, then **Manage Stages** to add stages in order,
each with a name (e.g. "Legal Review") and the specific people who can approve at that stage.

## Access Requests

If you can see a document exists but don't have enough access to open, edit, or manage it, you
don't need to ask around — request it directly from the document.

**Requesting access:** on the document's Details page, click **Request Access** (if you've already
got a request pending on that document, you'll see "Access request pending" instead, so you won't
accidentally submit a duplicate). Pick the access level you need — **View & Download**, **Edit**,
or **Full Control** — and give a short **Reason**, then **Submit Request**.

**What happens next:** the request goes to whoever can resolve it for that document — an Admin or
the document's Department Head. They'll see it under **Access Requests** in the main menu, on a
screen listing every pending request with the document, requester, level requested, the
document's *current* classification, and the stated reason. They can **Grant** or **Deny** it,
optionally with a note. If a document's classification changed after you submitted your request,
the resolver sees that flagged explicitly — so a request judged against outdated sensitivity won't
get rubber-stamped.

You'll know your request was resolved because you'll either gain the access you asked for, or the
request will simply no longer show as pending.

## Groups

Groups are a way an administrator can grant the same document permissions to a set of people at
once, instead of configuring access user-by-user. If you've been added to a group, you may find
you can see or edit documents that were never shared with you individually — that's the group
permission at work, not an error.

Group membership and the permissions granted to a group are managed entirely by administrators
(Admin menu → **Groups**). There's currently no screen where you can see which group(s) you
personally belong to — if you're unsure why you have access to something, ask your administrator.

## Quick reference

| I want to... | Where to go |
|---|---|
| Set how sensitive a new document is | "Information Classification" field on the Add Document form |
| Require multiple sign-offs before publishing | "Workflow Template" field on the Add/Edit Document form |
| See how far along a document's approval is | The document's Details page — "Approval Workflow" row |
| Approve or reject a document at my stage | The document's Details page — **Approve**/**Reject** buttons |
| Ask for access to a document I can't fully use | **Request Access** on that document's Details page |
| Review pending access requests (Admin/Dept Head) | **Access Requests** in the main menu |
| Find out why I can access something unexpectedly | Ask your administrator — it may be a Group permission |
