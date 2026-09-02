<?php

include('../../../inc/includes.php');

use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\NotFoundHttpException;

Session::checkLoginUser();

if (!PluginConfigfilessccglpiComputertab::canView()) {
    throw new AccessDeniedHttpException();
}

$computers_id = (int) ($_GET['computers_id'] ?? 0);
$computer     = new Computer();
if ($computers_id <= 0 || !$computer->getFromDB($computers_id)) {
    throw new NotFoundHttpException();
}

$documents = (new PluginConfigfilessccglpiDocumentLocator())->findLogbookForComputer($computer);
if (empty($documents)) {
    throw new NotFoundHttpException();
}

$path = GLPI_DOC_DIR . '/' . $documents[0]['filepath'];
if (!is_file($path) || !is_readable($path)) {
    throw new NotFoundHttpException();
}

$raw        = (string) file_get_contents($path);
$embeddable = (new PluginConfigfilessccglpiHtmlExtractor())->buildEmbeddableDocument($raw);
$srcdoc     = htmlescape($embeddable);
$title      = htmlescape(sprintf(__('Logbook - %s', 'configfilessccglpi'), $computer->fields['name']));

Html::header($title, $_SERVER['PHP_SELF'], 'assets', 'Computer');
echo "<iframe sandbox='allow-same-origin' "
    . "style='width:100%;height:calc(100vh - 150px);border:0;display:block' "
    . "srcdoc=\"{$srcdoc}\"></iframe>";
Html::footer();
