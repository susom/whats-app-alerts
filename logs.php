<?php
namespace Stanford\WhatsAppAlerts;
/** @var WhatsAppAlerts $module */

# Get all logs
$fields = [
    "log_id",
    // "trigger",
    "trigger_id",
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
print "</tbody></table>";
?>
<script>
    $('#logs').dataTable();
</script>
