<?php

require_once "DataDictionaryRevisions.php";

/**
 * Display REDCap header.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

/**
 * Check whether two revisions have been selected
 */
if (isset($_POST["dictionaries"]))
{
    $revision_one = $_POST["dictionaries"][0];
    $revision_two = $_POST["dictionaries"][1];
}

$data_dictionary_revisions = new \BCCHR\DataDictionaryRevisions\DataDictionaryRevisions();

/**
 * Retrieve all revisions of the data dictionary
 */
$previous_versions = $data_dictionary_revisions->getAllRevisions();
?>
<html>
    <head>
        <style>
            table {
                border-collapse: collapse;
            }

            table, th, td {
                border: 1px solid black;
            }

            th, td {
                padding: 5px;
            }

            th {
                background-color: lightgrey;
            }

            #revision-history-header:hover {
                background-color: grey;
            }
        </style>
    </head>
    <body>
        <h4>Data Dictionary Revisions</h4>
        <?php
            /**
             * If there are no revisions of the data dictionary then display a message indicating so, else print a table of all revisions.
             */
            if (empty($previous_versions)): 
        ?>
            <p>There must be at least two revisions of the data dictionary to use this plugin.</p>
        <?php else: ?>
            <p>
                The table contains all production revisions of the data dictionary for this project. When two revisions are selected, then data comparing them to each other will display.
            </p>
            <p><b>Select two data dictionary revisions to compare:</b></p>
            <form action="" method="post" id="dictionaries-form">
                <table style="margin-bottom:20px">
                        <thead>
                            <th colspan="4" id="revision-history-header"><b>Project Revision History</b> <span class="fas fa-caret-up" style=""></span><span class="fas fa-caret-down" style="display:none"></span></th>
                        </thead>
                        <tbody class="collapsible">
                        <?php 
                            foreach ($previous_versions as $index => $version) { 
                                print "<tr>";
                                print "<td style='width: 10px'><input type='checkbox' value='" . $version["id"] . "' name='dictionaries[]'></td>";
                                print "<td>" . $version["label"] . "</td>";
                                print "<td>" . $version["ts_approved"] . "</td>";
                                if ($index == sizeof($previous_versions) - 1)
                                {
                                    print "<td><p style='margin:0px'>Moved to production by <b>" . $version["approver"] . "</b></p></td>";
                                }
                                else if ($version["automatic_approval"] === "1")
                                {
                                    print "<td><p style='margin:0px'>Requested by <b>" . $version["requester"] . "</b></p><p style='margin:0px'>Approved automatically</b></p></td>";
                                }
                                else
                                {
                                    print "<td><p style='margin:0px'>Requested by <b>" . $version["requester"] . "</b></p><p style='margin:0px'>Approved by <b>" . $version["approver"] . "</b></p></td>";
                                }
                                print "</tr>";
                            }
                        ?>
                        </tbody>
                </table>
            </form>
            <?php
            /**
             * If two revisions have been selected, then render table of changes.
             */ 
            if (isset($revision_one) && isset($revision_two))
            {
                $key_one = array_search($revision_one, array_column($previous_versions, "id"));
                $key_two = array_search($revision_two, array_column($previous_versions, "id"));
                print "<h6 style='margin-bottom:20px'>Comparing <u><b>" . $previous_versions[$key_one]["label"] . "</b></u> to <u><b>" . $previous_versions[$key_two]["label"] . "</b></u></h6>";
                $data_dictionary_revisions->renderChangesTable($revision_one, $revision_two);
            }
        endif; ?>
    </body>
</html>
<script>
    /**
     * JS to submit the form when two revisions have been selected
     */
    $('input').on('change', function(evt) {
        if($('input:checked').length >= 2) {
            $('#dictionaries-form').submit();
        }
    });

    $('#revision-history-header').click(function() {
        $('.fa-caret-down').toggle();
        $('.fa-caret-up').toggle();
        $('.collapsible').toggle();
    });
</script>
<?php
/**
 * Display the footer.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';