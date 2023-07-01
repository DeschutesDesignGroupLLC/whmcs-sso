<?php

namespace App\Http\Controllers;

use Whmcs\Database\Capsule;

class AdminController extends Controller
{
    /**
     * @return string|void
     */
    public function index()
    {
        $html =
<<<'HTML'
<script type="text/javascript" src="/assets/js/jquerytt.js"></script>
<form method='post' action='/admin/addonmodules.php?module=sso'>
    <div class="tablebg">
        <table id="sortabletbl0" class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
            <tbody>
                <tr>
                    <th width="1%"><input type="checkbox" id="checkall0" data-ol-has-click-handler=""></th>
                    <th width="5%"><a href="/admin/addonmodules.php?module=sso&orderby=id">ID</a> 
                        <img src="images/desc.gif" class="absmiddle">
                    </th>
                    <th width="10%"><a href="/admin/addonmodules.php?module=sso&orderby=firstname">First Name</a></th>
                    <th width="10%"><a href="/admin/addonmodules.php?module=sso&orderby=lastname">Last Name</a></th>
                    <th width="20%"><a href="/admin/addonmodules.php?module=sso&orderby=email">Email</a></th>
                    <th width="15%"><a href="/admin/addonmodules.php?module=sso&orderby=sub">Sub</a></th>
                    <th width="30%"><a href="/admin/addonmodules.php?module=sso&orderby=access_token">Access Token</a></th>
                </tr>

HTML;
        foreach (Capsule::table('mod_sso_members')->join('tblusers', 'mod_sso_members.user_id', '=', 'tblusers.id')->get() as $link) {
            $html .=
<<<HTML
                <tr> 
                    <td><input type='checkbox' name='selectedclients[]' value="$link->user_id" class='checkall'></td>
                    <td><a href="clientssummary.php?userid={$link->user_id}">{$link->user_id}</a></td>
                    <td><a href="clientssummary.php?userid={$link->user_id}">{$link->first_name}</a></td>
                    <td><a href="clientssummary.php?userid={$link->user_id}">{$link->last_name}</a></td>
                    <td><a href="clientssummary.php?userid={$link->user_id}">{$link->email}</a></td>
                    <td>{$link->sub}</td>
                    <td style='max-width: 100px'><span class='truncate' style='display: block;'>{$link->access_token}</span></td>
                </tr>
HTML;
        }
        $html .=
<<<'HTML'
            </tbody>
        </table>
    </div>
    <input type="submit" value="Unlink SSO Account" class="btn btn-danger">
</form>

HTML;

        return $html;
    }

    /**
     * @return void
     */
    public function store()
    {
        $this->ssoService->removeSsoConnection($_POST['selectedclients']);

        $this->redirectService->redirectToLocation('/admin/addonmodules.php?module=sso');
    }
}
