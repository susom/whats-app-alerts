<?php
namespace Stanford\WhatsAppAlerts;
/** @var WhatsAppAlerts $module */

?>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs4/dt-1.10.23/b-1.6.5/b-colvis-1.6.5/b-html5-1.6.5/b-print-1.6.5/r-2.2.7/rg-1.1.2/sl-1.3.1/datatables.min.css"/>
<script type="text/javascript" src="https://cdn.datatables.net/v/bs4/dt-1.10.23/b-1.6.5/b-colvis-1.6.5/b-html5-1.6.5/b-print-1.6.5/r-2.2.7/rg-1.1.2/sl-1.3.1/datatables.min.js"></script>
<?php

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
