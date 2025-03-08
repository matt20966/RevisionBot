<?php
require_once('Models/RevisionInfoDataSet.php');
$revisionInfo = new RevisionInfoDataSet();
$data = $revisionInfo->getAllInfo();
echo json_encode($data);