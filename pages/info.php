<?php
namespace Stanford\WhatsAppAlerts;
/** @var WhatsAppAlerts $module */

/**
 * This is a control panel info page that helps with configuration of Twilio for the instance
 */


loadJS('Libraries/clipboard.js');
?>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs4/dt-1.10.23/b-1.6.5/b-colvis-1.6.5/b-html5-1.6.5/b-print-1.6.5/r-2.2.7/rg-1.1.2/sl-1.3.1/datatables.min.css"/>
<script type="text/javascript" src="https://cdn.datatables.net/v/bs4/dt-1.10.23/b-1.6.5/b-colvis-1.6.5/b-html5-1.6.5/b-print-1.6.5/r-2.2.7/rg-1.1.2/sl-1.3.1/datatables.min.js"></script>
<script type="text/javascript">
    // Copy-to-clipboard action
    var clipboard = new Clipboard('.btn-clipboard');
    // Copy the public survey URL to the user's clipboard
    function copyUrlToClipboard(ob) {
        // Create progress element that says "Copied!" when clicked
        var rndm = Math.random()+"";
        var copyid = 'clip'+rndm.replace('.','');
        var clipSaveHtml = '<span class="clipboardSaveProgress" id="'+copyid+'">Copied!</span>';
        $(ob).after(clipSaveHtml);
        $('#'+copyid).toggle('fade','fast');
        setTimeout(function(){
            $('#'+copyid).toggle('fade','fast',function(){
                $('#'+copyid).remove();
            });
        },2000);
    }
</script>
<div class="card">
    <div class="card-header">
        <div class="card-title">Twilio Inbound Url</div>
    </div>

    <div class="card-body">
        <p>The following url should be set in Twilio for inbound requests for all phone numbers associated with this project:</p>
        <input id="inbound_url" value="<?php echo $module->getInboundUrl() ?>" onclick="this.select();" readonly="readonly" class="staticInput p-2" style="width:80%"/>
        <button class="btn btn-defaultrc btn-clipboard" onclick="copyUrlToClipboard(this);" title="Copy to clipboard" data-clipboard-target="#inbound_url" style="padding:3px 8px 3px 6px;"><i class="fas fa-paste"></i></button>
    </div>
</div>


<?php



exit();


# Get all logs
$fields = [
    "log_id",
    // "source",
    "source_id",
    "timestamp",
    "record",
    "number",
    "message",
    "event_id",
    "event_name",
    "instance",
    "log_field",
    "log_event_id",
    "sid",
    "status",
    "status_update",
    "status_modified"
];

$project_id = $module->getProjectId();
$sql = "select " . implode(",", $fields) . " where project_id = ?";
$module->emDebug($sql);
$q = $module->queryLogs($sql,  [ $project_id ]);
$module->emDebug($q);

?>
<h3>What's App Logs</h3>
<?php

print "<table id='logs'><thead><tr><th>" . implode("</th><th>", $fields) . "</th></tr></thead><tbody>";
while ($row = db_fetch_assoc($q)) {
    print "<tr><td>" . implode("</td><td>",$row) . "</td></tr>";
}
print "</tbody></table>\n\n";
?>
<script>
    $(document).ready( function() {
        // console.log('here');
        $('#logs').DataTable({
            select: true
        });
    });
</script>
