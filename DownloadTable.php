<?php
require_once "DataDictionaryRevisions.php";
$data_dictionary_revisions = new \BCCHR\DataDictionaryRevisions\DataDictionaryRevisions();
$data_dictionary_revisions->getDownload($_POST["revision_one"], $_POST["revision_two"]);
?>