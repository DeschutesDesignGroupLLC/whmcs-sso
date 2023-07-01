<?php

namespace App\Http\Controllers;

class AdminController extends Controller
{
    public function index()
    {
        // If we have an unlink action
        if ($_GET['action'] === 'unlink') {

            // If we have clients to unlink
            if ($_POST['selectedclients'] or $_GET['userid']) {

                // Compose array of clients to delete
                $delete = array_filter(array_merge(is_array($_POST['selectedclients']) ? array_values($_POST['selectedclients']) : [], [$_GET['userid']]));

                // Try and delete the members
                try {

                    // Delete the members
                    Capsule::table('mod_sso_members')->whereIn('user_id', $delete)->delete();

                    // Redirect to reset
                    header('Location: /admin/addonmodules.php?module=sso');
                    exit;
                }

                // We got an error
                catch (\Exception $exception) {
                    // Log the error
                    logActivity('SSO: Unlink Exception - '.$exception->getMessage());
                }
            }
        }

        // Output our table - start
        echo '<script type="text/javascript" src="/assets/js/jquerytt.js"></script>';
        echo "<form method='post' action='/admin/addonmodules.php?module=sso&action=unlink'>";
        echo '<div class="tablebg"><table id="sortabletbl0" class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3"><tbody>';
        echo '<tr><th width="1%"><input type="checkbox" id="checkall0" data-ol-has-click-handler=""></th><th width="5%"><a href="/admin/addonmodules.php?module=sso&orderby=id">ID</a> <img src="images/desc.gif" class="absmiddle"></th><th width="10%"><a href="/admin/addonmodules.php?module=sso&orderby=firstname">First Name</a></th><th width="10%"><a href="/admin/addonmodules.php?module=sso&orderby=lastname">Last Name</a></th><th width="20%"><a href="/admin/addonmodules.php?module=sso&orderby=email">Email</a></th><th width="15%"><a href="/admin/addonmodules.php?module=sso&orderby=sub">Sub</a></th><th width="30%"><a href="/admin/addonmodules.php?module=sso&orderby=access_token">Access Token</a></th></tr>';

        // Get our client login links
        foreach (Capsule::table('mod_sso_members')->join('tblusers', 'mod_sso_members.user_id', '=', 'tblusers.id')->get() as $link) {

            // Print a row
            echo '<tr>';
            echo "<td><input type='checkbox' name='selectedclients[]' value='$link->user_id' class='checkall'></td>";
            echo "<td><a href=\"clientssummary.php?userid=$link->user_id\">$link->user_id</a></td>";
            echo "<td><a href=\"clientssummary.php?userid=$link->user_id\">$link->first_name</a></td>";
            echo "<td><a href=\"clientssummary.php?userid=$link->user_id\">$link->last_name</a></td>";
            echo "<td><a href=\"clientssummary.php?userid=$link->user_id\">$link->email</a></td>";
            echo "<td>$link->sub</td>";
            echo "<td style='max-width: 100px'><span class='truncate' style='display: block;'>$link->access_token</span></td>";
            echo '</tr>';
        }

        // Output the table - end
        echo '</tbody></table></div>';

        // Output button
        echo 'With Selected: ';
        echo '<input type="submit" value="Unlink Okta Account" class="btn btn-danger">';
        echo '</form>';
    }
}
