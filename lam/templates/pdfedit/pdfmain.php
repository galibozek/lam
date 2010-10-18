<?php
/*
$Id$

  This code is part of LDAP Account Manager (http://www.ldap-account-manager.org/)
  Copyright (C) 2003 - 2006  Michael Duergner
                2005 - 2010  Roland Gruber

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

/**
* This is the main window of the pdf structure editor.
*
* @author Michael Duergner
* @author Roland Gruber
* @package PDF
*/

/** security functions */
include_once("../../lib/security.inc");
/** access to PDF configuration files */
include_once("../../lib/pdfstruct.inc");
/** LDAP object */
include_once("../../lib/ldap.inc");
/** for language settings */
include_once("../../lib/config.inc");
/** module functions */
include_once("../../lib/modules.inc");

// start session
startSecureSession();

// die if no write access
if (!checkIfWriteAccessIsAllowed()) die();

setlanguage();

// Unset pdf structure definitions in session if set
if(isset($_SESSION['currentPDFStructure'])) {
	unset($_SESSION['currentPDFStructure']);
	unset($_SESSION['availablePDFFields']);
	unset($_SESSION['currentPageDefinitions']);
}

// check if user is logged in, if not go to login
if (!$_SESSION['ldap'] || !$_SESSION['ldap']->server()) {
	metaRefresh("../login.php");
	exit;
}

// check if new template should be created
if(isset($_POST['createNewTemplate'])) {
	metaRefresh('pdfpage.php?type=' . $_POST['scope']);
	exit();
}

$scopes = $_SESSION['config']->get_ActiveTypes();
$sortedScopes = array();
for ($i = 0; $i < sizeof($scopes); $i++) {
	$sortedScopes[$scopes[$i]] = getTypeAlias($scopes[$i]);
}
natcasesort($sortedScopes);

// get list of account types
$availableScopes = '';
$templateClasses = array();
foreach ($sortedScopes as $scope => $title) {
	$templateClasses[] = array(
		'scope' => $scope,
		'title' => $title,
		'templates' => "");
	$availableScopes[$title] = $scope;
}
// get list of templates for each account type
for ($i = 0; $i < sizeof($templateClasses); $i++) {
	$templateClasses[$i]['templates'] = getPDFStructureDefinitions($templateClasses[$i]['scope']);
}

// check if a template should be edited
for ($i = 0; $i < sizeof($templateClasses); $i++) {
	if (isset($_POST['editTemplate_' . $templateClasses[$i]['scope']]) || isset($_POST['editTemplate_' . $templateClasses[$i]['scope'] . '_x'])) {
		metaRefresh('pdfpage.php?type=' . $templateClasses[$i]['scope'] . '&edit=' . $_POST['template_' . $templateClasses[$i]['scope']]);
		exit;
	}
}
// check if a profile should be deleted
for ($i = 0; $i < sizeof($templateClasses); $i++) {
	if (isset($_POST['deleteTemplate_' . $templateClasses[$i]['scope']]) || isset($_POST['deleteTemplate_' . $templateClasses[$i]['scope'] . '_x'])) {
		metaRefresh('pdfdelete.php?type=' . $templateClasses[$i]['scope'] . '&delete=' . $_POST['template_' . $templateClasses[$i]['scope']]);
		exit;
	}
}

$container = new htmlTable();

include '../main_header.php';
?>
<div class="userlist-bright smallPaddingContent">
<form action="pdfmain.php" method="post">
	<?php
		$container->addElement(new htmlTitle(_('PDF editor')), true);
	
		if (isset($_GET['savedSuccessfully'])) {
			$message = new htmlStatusMessage("INFO", _("PDF structure was successfully saved."), htmlspecialchars($_GET['savedSuccessfully']));
			$message->colspan = 10;
			$container->addElement($message, true);
		}
		if (isset($_GET['deleteFailed'])) {
			$message = new htmlStatusMessage('ERROR', _('Unable to delete PDF structure!'), getTypeAlias($_GET['deleteScope']) . ': ' . htmlspecialchars($_GET['deleteFailed']));
			$message->colspan = 10;
			$container->addElement($message, true);
		}
		if (isset($_GET['deleteSucceeded'])) {
			$message = new htmlStatusMessage('INFO', _('Deleted PDF structure.'), getTypeAlias($_GET['deleteScope']) . ': ' . htmlspecialchars($_GET['deleteSucceeded']));
			$message->colspan = 10;
			$container->addElement($message, true);
		}
		
		// new template
		$container->addElement(new htmlSubTitle(_('Create a new PDF structure')), true);
		$newPDFContainer = new htmlTable();
		$newScopeSelect = new htmlSelect('scope', $availableScopes);
		$newScopeSelect->setHasDescriptiveElements(true);
		$newScopeSelect->setWidth('15em');
		$newPDFContainer->addElement($newScopeSelect);
		$newPDFContainer->addElement(new htmlSpacer('10px', null));
		$newPDFContainer->addElement(new htmlButton('createNewTemplate', _('Create')));
		$container->addElement($newPDFContainer, true);
		$container->addElement(new htmlSpacer(null, '10px'), true);
		
		// existing templates
		$container->addElement(new htmlSubTitle(_("Manage existing PDF structures")), true);
		$existingContainer = new htmlTable();
		for ($i = 0; $i < sizeof($templateClasses); $i++) {
			$existingContainer->addElement(new htmlImage('../../graphics/' . $templateClasses[$i]['scope'] . '.png'));
			$existingContainer->addElement(new htmlSpacer('3px', null));
			$existingContainer->addElement(new htmlOutputText($templateClasses[$i]['title']));
			$existingContainer->addElement(new htmlSpacer('3px', null));
			$select = new htmlSelect('template_' . $templateClasses[$i]['scope'], $templateClasses[$i]['templates']);
			$select->setWidth('15em');
			$existingContainer->addElement($select);
			$existingContainer->addElement(new htmlSpacer('3px', null));
			$exEditButton = new htmlButton('editTemplate_' . $templateClasses[$i]['scope'], 'edit.png', true);
			$exEditButton->setTitle(_('Edit'));
			$existingContainer->addElement($exEditButton);
			$exDelButton = new htmlButton('deleteTemplate_' . $templateClasses[$i]['scope'], 'delete.png', true);
			$exDelButton->setTitle(_('Delete'));
			$existingContainer->addElement($exDelButton, true);
			$existingContainer->addElement(new htmlSpacer(null, '10px'), true);
		}
		$container->addElement($existingContainer, true);
		
		$tabindex = 1;
		parseHtml(null, $container, array(), false, $tabindex, 'user');
	?>
</form>
</div>
<?php
	include '../main_footer.php';
?>
