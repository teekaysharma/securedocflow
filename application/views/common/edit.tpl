<script type="text/javascript" src="{$g_base_url}js/functions.js"></script>

<!-- file upload formu using ENCTYPE -->
<form id="addeditform" name="main" class="display dataTable" action="edit" method="POST" enctype="multipart/form-data" onsubmit="return checksec(); ">
    {$csrf_token_field}
    <input type="hidden" id="db_prefix" value="{$db_prefix}" />
<table border="0" cellspacing="5" cellpadding="5">
{assign var='i' value='0'}    
{foreach from=$t_name item=name name='loop1'}
    <input type="hidden" id="secondary{$i|escape}" name="secondary{$i|escape:'html'}" value="" /> <!-- CHM hidden and onsubmit added-->
    <input type="hidden" id="tablename{$i|escape}" name="tablename{$i|escape:'html'}" value="{$name|escape:'html'}" /> <!-- CHM hidden and onsubmit added-->
    {assign var='i' value=$i+1}
{/foreach}
    <input type="hidden" id="id" name="id" value="{$file_id|escape:'html'}" />
    <input id="i_value" type="hidden" name="i_value" value="{$i|escape:'html'}" /> <!-- CHM hidden and onsubmit added-->
    <tr>
		<td>{$g_lang_label_name}</td>
		<td colspan="3"><b>{$realname|escape:'html'}</b></td>
    </tr>
    
{if $is_admin == true }
    <tr>

        <td>
            {$g_lang_editpage_assign_owner}
        </td>
        <td>
            <select name="file_owner">
            {foreach from=$avail_users|smarty:nodefaults item=user}
                <option value="{$user.id|escape}" {if $pre_selected_owner eq $user.id}selected='selected'{/if}>{$user.last_name|escape:'html'}, {$user.first_name|escape:'html'}</option>
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
            {foreach from=$avail_depts|smarty:nodefaults item=dept}
                <option value="{$dept.id|escape}" {if $pre_selected_department eq $dept.id}selected='selected'{/if}>{$dept.name|escape:'html'}</option>
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
            {foreach from=$cats_array|smarty:nodefaults item=cat}
                <option value="{$cat.id|escape}" {if $pre_selected_category eq $cat.id}selected='selected'{/if}>{$cat.name|escape:'html'}</option>
            {/foreach}
            </select>
        </td>
    </tr>

    <tr>
        <td>
            Current Classification
        </td>
        <td colspan=3>
            <b>{$current_classification|escape:'html'}</b>
        </td>
    </tr>
{if $is_admin == true}
    <tr>
        <td>
            Reclassify To
        </td>
        <td colspan=3>
            <select name="new_classification">
                <option value="">-- Keep current classification --</option>
                <option value="Public">Public</option>
                <option value="Internal">Internal</option>
                <option value="Limited">Limited</option>
                <option value="Sensitive">Sensitive</option>
                <option value="Highly sensitive">Highly sensitive</option>
            </select>
        </td>
    </tr>
    <tr>
        <td>
            Reason for Reclassification
        </td>
        <td colspan=3>
            <input type="text" name="new_classification_reason" size="50" placeholder="Required if changing classification above" />
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
                <option value="{$wft.id|escape}" {if $current_workflow_template_id eq $wft.id}selected='selected'{/if}>{$wft.name|escape:'html'} ({$wft.stage_count|escape} stage{if $wft.stage_count ne 1}s{/if})</option>
                {/foreach}
            </select>
            &nbsp;Required for Sensitive (2+ stages) and Highly sensitive (3+ stages).
            {if $current_workflow_template_id}
                &nbsp;Currently at stage {$current_workflow_stage_number|escape}.
            {/if}
        </td>
    </tr>
{/if}
{if $can_set_high_classification}
    <tr>
        <td>
            Valid Until
        </td>
        <td colspan=3>
            <input type="date" name="valid_until" value="{$current_valid_until|escape:'html'}" />
            &nbsp;(leave blank — never expires)
        </td>
    </tr>
    <tr>
        <td>
            Reason for Validity Change
        </td>
        <td colspan=3>
            <input type="text" name="valid_until_reason" size="50" placeholder="Required if setting/changing validity date above" />
        </td>
    </tr>
{/if}
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
        <td colspan="3"><input tabindex="5" type="Text" name="description" size="50" value="{$description|escape:'html'}"/></td>
    </tr>

    <tr>
        <td>
            <a class="body" href="help.html#Add_File_-_Comment" onClick="return popup(this, 'Help')" style="text-decoration:none">{$g_lang_label_comment}</a>
        </td>
        <td colspan="3"><textarea tabindex="6" name="comment" rows="4" onchange="this.value=enforceLength(this.value, 255);">{$comment|escape:'html'}</textarea></td>
    </tr>