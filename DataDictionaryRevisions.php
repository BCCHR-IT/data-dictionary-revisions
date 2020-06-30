<?php

namespace BCCHR\DataDictionaryRevisions;

use REDCap;
use Project;
use MetaData;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DataDictionaryRevisions extends \ExternalModules\AbstractExternalModule {

    /**
     * Class variables
     * 
     * @var Array $latest_metadata      The data dictionary of the newer revision
     * @var Array $furthest_metadata    The data dictionary of the older revision
     * @var Array $metadata_changes     Differences between $latest_metadata and $furthest_metadata that include fields deleted, added, and modified
     * @var Array $ui_ids               User ids that have previously been identified within getUsernameFirstLast()
     */
    private $latest_metadata;
    private $furthest_metadata;
    private $metadata_changes;
    private $ui_ids;

    /**
     * Class constructor
     * 
     * @since 1.0
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Sets class variables, except for $ui_ids.
     * 
     * @param String $revision_one  A revision id of one of the previous data dictionaries(y). Assume that $revision_one always contains id of dictionary that comes after $revision_two
     * @param String $revision_two  A revision id of one of the previous/current data dictionaries(y).
     * @since 2.0
     */
    private function setMetadataVariables($revision_one, $revision_two)
    {   
        if ($revision_one == "current")
        {
            $this->latest_metadata = REDCap::getDataDictionary("array");
            $this->furthest_metadata = MetaData::getDataDictionary("array", true, array(), array(), false, false, $revision_two);
        }
        else
        {
            $this->latest_metadata = MetaData::getDataDictionary("array", true, array(), array(), false, false, $revision_one);
            $this->furthest_metadata = MetaData::getDataDictionary("array", true, array(), array(), false, false, $revision_two);
        }

        $this->metadata_changes = array();

        // Check new and modified fields.
        foreach($this->latest_metadata as $field => $metadata)
        {
            // Check to see if values are different from existing field. If they are, don't include in new array.
            if (!isset($this->furthest_metadata[$field]) || $metadata !== $this->furthest_metadata[$field]) {
                $this->metadata_changes[$field] = $metadata;
            }
        }

        // Check deleted fields.
        $current_fields = array_keys($this->latest_metadata);
        $deleted_fields = array_filter($this->furthest_metadata, function($field_name) use($current_fields) {
            return !in_array($field_name, $current_fields);
        }, ARRAY_FILTER_USE_KEY);

        $this->metadata_changes = array_merge($this->metadata_changes, $deleted_fields);
    }
    
    /**
     * Returns the following detils about differences between current and previous data dictionary:
     *      - Number of fields added
     *      - Number of fields deleted
     *      - Number of fields modified
     *      - Total field count before commit
     *      - Total field count after commit
     * 
     * @since 1.0
     * @return Array An associative array of the above information, representing differences between data dictionary versions.
     */
    private function getDetails()
    {
        $details = array();
        $num_fields_added = 0;
        $num_fields_deleted = 0;
        $num_fields_modified = 0;
        $total_fields_before = sizeof($this->furthest_metadata);
        $total_fields_after = sizeof($this->latest_metadata);

        foreach($this->metadata_changes as $field => $metadata)
        {
            $new_metadata = $this->latest_metadata[$field];
            $old_metadata = $this->furthest_metadata[$field];

            // Check for fields added.
            if (!$old_metadata)
            {
                $num_fields_added++;
            }
            // Check for deleted fields.
            else if (!$new_metadata)
            {
                $num_fields_deleted++;
            }
            // Check for fields modified.
            else
            {
                $differences = array_diff_assoc($new_metadata, $old_metadata);
                if (!empty($differences))
                {
                    $num_fields_modified++;
                }
            }
        }
        
        return array(
            "num_fields_added" => $num_fields_added,
            "num_fields_deleted" => $num_fields_deleted,
            "num_fields_modified" => $num_fields_modified,
            "total_fields_before" => $total_fields_before,
            "total_fields_after" => $total_fields_after
        );
    }

    /**
     * Retrieves the username and full name from the given user id.
     * 
     * @param String $ui_ud The user id of a REDCap user.
     * @since 1.0
     * @return String The username and full name associated with the given user id, formatted as "<username> (<first name> <last name>)". Returns "Unknown" in event of error.
     */
    private function getUsernameFirstLast($ui_id)
    {
        // Must be numeric
        if (!is_numeric($ui_id)) {
            return "Unknown";
        }
        
        // If already called, retrieve from array instead of querying
        if (isset($this->ui_ids[$ui_id])) {
            return $this->ui_ids[$ui_id];
        } 
        
        // Get from table
        $sql = "select concat(username,' (',user_firstname,' ',user_lastname,')') as user from redcap_user_information where ui_id = $ui_id";
        $result = $this->query($sql);
        if ($result = $this->query($sql))
        {
            while ($row = $result->fetch_object()){
                // Add to array if called again
                $this->ui_ids[$ui_id] = $row->user;
            }
            $result->close();
        }

        // Return query result
        return $this->ui_ids[$ui_id];
    }

    /**
     * Creates CSV of the details regarding Table of Changes, then outputs it to the browser for downloading.
     * 
     * @param String $revision_one  A revision id of one of the previous data dictionaries(y). Assume that $revision_one always contains id of dictionary that comes after $revision_two
     * @param String $revision_two  A revision id of one of the previous/current data dictionaries(y).
     * @since 3.0
     */
    public function getDownload($revision_one, $revision_two)
    {
        $this->setMetadataVariables($revision_one, $revision_two);
        if (sizeof($this->metadata_changes) > 0)
        {
            $headers = array_keys(current($this->latest_metadata));
            $headers[] = "change_status";
            $headers[] = "changed_fields";
            $headers[] = "change_details (field: old values)";
            
            $filename = "comparison_of_changes.csv";

            header('Content-Description: File Transfer');
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $file = fopen("php://output", "w");

            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // Use UTF-8 encoding

            // Write headers to file
            if (fputcsv($file, $headers) == "FALSE")
            {
                REDCap::logEvent("Data Dictionary Revisions External Module - Error", "Error writing headers to $filename.", null, null, null, $this->getProjectId());
            }

            foreach($this->metadata_changes as $field => $metadata) 
            {
                $metadata = array_values($metadata);
                $csv_row = array();
                $changed_fields = "";
                $change_details = "";

                foreach($metadata as $i => $attr)
                {
                    $attr = strip_tags($attr);
                    if (is_null($this->furthest_metadata[$field]) || is_null($this->latest_metadata[$field])) // field value is missing
                    { 
                        $value = $attr ? $attr : "";
                        $csv_row[] = $value;
                    }
                    else
                    {
                        $old_value = strip_tags($this->furthest_metadata[$field][$headers[$i]]);
                        if ($attr != $old_value)
                        {
                            $value = $attr ? $attr : "";
                            $old_value = $old_value ? $old_value : "";
                            $csv_row[] = $value;
                            $changed_fields .= $headers[$i] . "\r\n";
                            $change_details .= $headers[$i] . ": " . $old_value . "\r\n";
                        }
                        else
                        {
                            $value = $attr ? $attr : "";
                            $csv_row[] = $value;
                        }
                    }
                }

                if (is_null($this->furthest_metadata[$field])) // New Field
                {
                    $csv_row[] = "New field";
                }
                else if (is_null($this->latest_metadata[$field])) // Deleted Field
                {
                    $csv_row[] = "Deleted field";
                }
                else
                {
                    $csv_row[] = "Field with changes";
                }

                $csv_row[] = $changed_fields;
                $csv_row[] = $change_details;

                if (fputcsv($file, $csv_row) == FALSE)
                {
                    REDCap::logEvent("Data Dictionary Revisions External Module - Error", "Error writing changes to $filename.", null, null, null, $this->getProjectId());
                }
            }

            if (fclose($file) === FALSE)
            {
                REDCap::logEvent("Data Dictionary Revisions External Module - Error", "Error closing $filename.", null, null, null, $this->getProjectId());
            }
        }
    }

    /**
     * Renders details, and table of differences between two versions of the data dictionary/metadata.
     * 
     * @param String $revision_one  A revision id of one of the previous data dictionaries(y). Assume that $revision_one always contains id of dictionary that comes after $revision_two
     * @param String $revision_two  A revision id of one of the previous/current data dictionaries(y).
     * @since 2.0
     */
    public function renderChangesTable($revision_one, $revision_two)
    {
        $this->setMetadataVariables($revision_one, $revision_two);
        $details = $this->getDetails();
        $headers = array_keys(current($this->latest_metadata));
        ?>
        <div class="row">
            <div  class="col-sm-12 col-md-3" style="margin-bottom:20px">
                <u><b>Details regarding changes between versions</b></u>
                <?php
                    if (!empty($details))
                    {
                        print "<ul>";
                        print "<li style='color: green'>Fields added: " . $details["num_fields_added"] . "</li>";
                        print "<li style='color: red'>Fields deleted: " . $details["num_fields_deleted"] . "</li>";
                        print "<li>Fields modified: " . $details["num_fields_modified"] . "</li>";
                        print "<li>Total fields <b>BEFORE</b> changes: " . $details["total_fields_before"] . "</li>";
                        print "<li>Total fields <b>AFTER</b> changes: " . $details["total_fields_after"] . "</li>";
                        print "</ul>";
                    }
                ?>
            </div>
            <div class="col-sm-12 col-md-3" style="margin-bottom:20px">
                <table cellspacing="0" cellpadding="0" border="1">
                    <tbody>
                        <tr>
                            <td style="padding: 5px; text-align: left; background-color: black !important; color: white !important; font-weight: bold;">
                                KEY for Comparison Table below
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 5px; text-align: left;">
                                White cell = no change
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 5px; text-align: left; background-color: #FFFF80 !important;">
                                Yellow cell = field changed (Black text = current value, <font color="#909090">Gray text = old value</font>)
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 5px; text-align: left; background-color: #7BED7B !important;">
                                Green cell = new project field
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 5px; text-align: left; background-color: #FE5A5A !important;">
                                Red cell = deleted project field
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            <?php if (empty($this->metadata_changes)) :?>
                <h4>Table of Changes</h4>
                <p>The data dictionaries are identical</p>
            <?php else: ?>
                <form action="<?php print $this->getUrl("DownloadTable.php"); ?>" method="post">
                <h4>Table of Changes <button class="btn btn-link" type="submit">Download</button></h4>
                <input name="revision_one" type="hidden" value="<?php print $revision_one;?>"></input><input name="revision_two" type="hidden" value="<?php print $revision_two;?>"></input>
                </form>
                <table>
                    <thead>
                        <tr>
                            <?php foreach($headers as $header) { print "<th><b>$header</b></th>"; } ?>
                        </tr>
                    </thead>
                    <?php 
                        foreach($this->metadata_changes as $field => $metadata) {
                            $html = "";

                            if (is_null($this->furthest_metadata[$field])) // New field
                            {
                                $html .= "<tr style='background-color:#7BED7B'>";
                            }
                            else if (is_null($this->latest_metadata[$field])) // Deleted field
                            {
                                $html .= "<tr style='background-color:#FE5A5A'>";
                            }
                            else
                            {
                                $html ="<tr>";
                            }

                            foreach($metadata as $key => $attr)
                            {
                                $attr = strip_tags($attr);
                                if (is_null($this->furthest_metadata[$field]) || is_null($this->latest_metadata[$field]))
                                { 
                                    $html .= "<td>" . ($attr ? $attr : "n/a") . "</td>";
                                }
                                else // Modified field
                                {
                                    $old_value = strip_tags($this->furthest_metadata[$field][$key]);
                                    if ($attr != $old_value)
                                    {
                                        $html .= "<td style='background-color:#FFFF80'><p>" . ($attr ? $attr : "(no value)") . "</p><p style='color:#aaa'>" . ($old_value ? $old_value : "(no value)"). "<p></td>";
                                    }
                                    else
                                    {
                                        $html .= "<td name='row[]'>" . ($attr ? $attr : "n/a") . "</td>";
                                    }
                                }
                            }

                            $html .= "</tr>";
                            print $html;
                        }
                    ?>
                </table>
            <?php endif?>
        </div>
        <?php
    }

    /**
     * Retrieves a list of all data dictionary revisions in the current project. The revisions are returned as
     * an associative array of arrays, where each revision is an array that contains the following information:
     *      - Label
     *      - Timestamp Approved
     *      - Requester
     *      - Approver
     * 
     * @since 1.0
     * @return Array An associative array of revisions in the current project.
     */
    public function getAllRevisions()
    {
        // Retrieves username associated with an id. 
        $this->ui_ids = array();

        /**
         * Retrieves list of previous versions of data dictionary.
         */
        $pid = $_GET["pid"];
        $previous_versions = array();
        $sql = "select p.pr_id, p.ts_approved, p.ui_id_requester, p.ui_id_approver,
                    if(l.description = 'Approve production project modifications (automatic)',1,0) as automatic
                    from redcap_metadata_prod_revisions p left join redcap_log_event l
                    on p.project_id = l.project_id and p.ts_approved*1 = l.ts
                    where p.project_id = $pid and p.ts_approved is not null order by p.pr_id";

        if ($result = $this->query($sql))
        {
            // Cycle through results
            $rev_num = 0;
            $num_rows = $result->num_rows;
            while ($row = $result->fetch_object())
            {
                if ($rev_num == 0)
                {
                    $previous_versions[] = array(
                        "id" => $row->pr_id,
                        "label" => "Moved to Production",
                        "requester" => $this->getUsernameFirstLast($row->ui_id_requester),
                        "approver" =>  $this->getUsernameFirstLast($row->ui_id_approver),
                        "automatic_approval" => $row->automatic,
                        "ts_approved" => $row->ts_approved,
                    );
                }
                else
                {
                    $previous_versions[] = array(
                        "id" => $row->pr_id,
                        "label" => "Production Revision #$rev_num",
                        "requester" => $this->getUsernameFirstLast($row->ui_id_requester),
                        "approver" =>  $this->getUsernameFirstLast($row->ui_id_approver),
                        "automatic_approval" => $row->automatic,
                        "ts_approved" => $row->ts_approved
                    );
                }

                if ($rev_num == $num_rows - 1)
                {
                    // Current revision will be mapped to timestamp and approver of last row
                    $current_revision_ts_approved =  $row->ts_approved;
                    $current_revision_approver = $this->getUsernameFirstLast($row->ui_id_approver);
                    $current_revision_requester = $this->getUsernameFirstLast($row->ui_id_requester);
                    $current_revision_automatic = $row->automatic;
                }

                $rev_num++;
            }

            $result->close();
            
            // Sort by most recent version.
            $previous_versions = array_reverse($previous_versions);
        }

        // Shift timestamps, approvers, requesters, and automatic approval down by one,
        // as the correct info for each one is when the previous version was archived. 
        $last_key = null;
        foreach($previous_versions as $key => $version)
        {
            if ($last_key !== null)
            {
                $previous_versions[$last_key]["ts_approved"] = $previous_versions[$key]["ts_approved"];
                $previous_versions[$last_key]["requester"] = $previous_versions[$key]["requester"];
                $previous_versions[$last_key]["approver"] = $previous_versions[$key]["approver"];
                $previous_versions[$last_key]["automatic_approval"] = $previous_versions[$key]["automatic_approval"];
            }
            $last_key = $key;
        }

        // Get correct production timestamp,
        // and the person who moved it to production
        if (!empty($previous_versions))
        {
            // Get correct production timestamp
            $sql = "select production_time from redcap_projects where project_id = $pid";
            if ($result = $this->query($sql))
            {
                while ($row = $result->fetch_object()){
                    $timestamp = $row->production_time;
                    $previous_versions[sizeof($previous_versions)-1]["ts_approved"] = $timestamp;
                }
                $result->close();
            }

            if (!empty($timestamp))
            {
                // Retrieve person who moved to production, as it's stored separately. 
                $sql = "select u.ui_id from redcap_user_information u, redcap_log_event l
                where u.username = l.user and l.description = 'Move project to production status' and l.project_id = $pid 
                and l.ts = '" . str_replace(array(' ',':','-'), array('','',''), $timestamp) . "' order by log_event_id desc limit 1";

                if ($result = $this->query($sql))
                {
                    while ($row = $result->fetch_object()){
                        $previous_versions[sizeof($previous_versions)-1]["approver"] = $this->getUsernameFirstLast($row->ui_id);
                    }
                    $result->close();
                }
            }
        }

        // Add current revision
        if (isset($current_revision_approver) && 
            isset($current_revision_ts_approved) && 
            isset($current_revision_automatic) && 
            isset($current_revision_requester))
        {
            array_unshift($previous_versions, array(
                "id" => "current",
                "label" => "Production Revision #$rev_num <b>(Current Revision)</b>",
                "requester" => $current_revision_requester,
                "approver" => $current_revision_approver,
                "automatic_approval" => $current_revision_automatic,
                "ts_approved" => $current_revision_ts_approved
            ));
        }

        return $previous_versions;
    }
}