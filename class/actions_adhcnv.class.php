<?php
/* Copyright (C) 2020 Florian HENRY
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_adhcnv.class.php
 * \ingroup adhcnv
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class Actionsdiscountrules
 */
class Actionsadhcnv
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;
	/**
	 * @var string Error
	 */
	public $error = '';
	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;


	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/*
	* Overloading the printPDFline function
	*
	* @param   array()         $parameters     Hook metadatas (context, etc...)
	* @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	* @param   string          $action         Current action (if set). Generally create or edit or null
	* @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	* @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	*/
	public function addMoreMassActions($parameters, &$model, &$action, $hookmanager)
	{
		global $langs, $conf;
		if (in_array($parameters['currentcontext'], array('memberlist'))) {
			$ret = '<option value="create_mailing">' . $langs->trans('Créer Emailing') . '</option>';
			$this->resprints = $ret;
		}

		return 0;

	}

	/*
	* Overloading the printPDFline function
	*
	* @param   array()         $parameters     Hook metadatas (context, etc...)
	* @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	* @param   string          $action         Current action (if set). Generally create or edit or null
	* @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	* @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	*/
	public function doActions($parameters, &$model, &$action, $hookmanager)
	{
		global $db, $conf, $user, $langs;
		if (in_array($parameters['currentcontext'], array('memberlist'))) {
			$massaction = GETPOST('massaction', 'alpha');
			if ($massaction == 'create_mailing') {
				require_once DOL_DOCUMENT_ROOT . '/comm/mailing/class/mailing.class.php';
				require_once DOL_DOCUMENT_ROOT . '/core/modules/mailings/modules_mailings.php';
				require_once DOL_DOCUMENT_ROOT . '/adherents/class/adherent.class.php';
				dol_include_once('/adhcnv/core/modules/adhcnv/adhcnv.modules.php');

				$adhIds = GETPOST('toselect', 'array');

				$error = 0;
				$adhMails = array();
				$cibles = array();

				$mailing = new Mailing($db);
				$mailing->title = 'Communication CNV ' . dol_print_date(dol_now());
				$mailing->sujet = 'Communication CNV ' . dol_print_date(dol_now());
				$mailing->statut = 0;
				$mailing->email_from = $conf->global->MAIN_MAIL_SMTPS_ID;
				$mailing->email_errorsto = $conf->global->MAIN_MAIL_SMTPS_ID;
				$mailing->email_replyto = $conf->global->MAIN_MAIL_SMTPS_ID;

				$mailing->body = 'Bonjour&nbsp;__OTHER2__ __LASTNAME__ __FIRSTNAME__<br />';
				$mailing->body .= '<br />';
				$mailing->body .= 'Ici mon texte<br />';
				$mailing->body .= '<br />';
				$mailing->body .= '<br />';
				$mailing->body .= '__CHECK_READ__ <i>à laisser pour avoir un accusé de lecture dans dolibarr</i><br />';
				$mailing->body .= 'Pour vous d&eacute;sinscrire : __UNSUBSCRIBE__';

				$result = $mailing->create($user);
				if ($result < 0) {
					setEventMessage($mailing->error, 'errors');
					$error++;
				}
				$result = $mailing->update($user);
				if ($result < 0) {
					setEventMessage($mailing->error, 'errors');
					$error++;
				}

				if (empty($error)) {
					foreach ($adhIds as $adhId) {
						$adh = new Adherent($db);
						$result = $adh->fetch($adhId);
						if ($result < 0) {
							setEventMessage($adh->error);
							$error++;
						}
						if (!empty($adh->email)) {
							if (!in_array($adh->email, $adhMails)) {
								$adhMails[$adh->email] = $adh->email;

								$cibles[] = array(
									'email'       => $adh->email,
									'fk_contact'  => null,
									'lastname'    => $adh->lastname,
									'firstname'   => $adh->firstname,
									'other'       =>
										($langs->transnoentities("Login") . '=' . $adh->login) . ';' .
										($langs->transnoentities("UserTitle") . '=' . ($adh->civility_id ? $langs->transnoentities("Civility" . $adh->civility_id) : '')) . ';' .
										($langs->transnoentities("DateEnd") . '=' . dol_print_date($this->db->jdate($adh->datefin), 'day')) . ';' .
										($langs->transnoentities("Company") . '=' . $adh->societe),
									'source_url'  => '<a href="' . DOL_URL_ROOT . '/adherents/card.php?rowid=' . $adh->id . '">' . img_object('', "user") . '</a>',
									'source_id'   => $adh->id,
									'source_type' => 'member'
								);
							}
						}
					}
				}
				if (count($cibles) > 0) {
					$mailingtarget = new mailing_adhcnv($db);
					$result = $mailingtarget->addTargetsToDatabase($mailing->id, $cibles);
					if ($result < 0) {
						setEventMessage($mailingtarget->error, 'errors');
					}
				}
				header("Location: " . dol_buildpath('/comm/mailing/card.php', 2) . "?id=" . $mailing->id . '&action=edit');
				exit;
			}
		}
	}


	/**
	 * Execute action
	 *
	 * @param array $parameters Array of parameters
	 * @param Object $pdfhandler PDF builder handler
	 * @param string $action 'add', 'update', 'view'
	 * @return  int                    <0 if KO,
	 *                                =0 if OK but we want to process standard actions too,
	 *                                >0 if OK and we want to replace standard actions.
	 */
	function afterPDFCreation($parameters, &$pdfhandler, &$action)
	{
		global $conf, $mysoc;

		$error = 0;
		if (!in_array($parameters['object']->element, array('facture', 'invoice'))) return 0;

		$outputlangs = $parameters['outputlangs'];

		$ret = 0;
		$deltemp = array();
		dol_syslog(get_class($this) . '::executeHooks action=' . $action);

		// Get properties of PDF $file
		$formatarray = pdf_getFormat();
		$page_largeur = $formatarray['width'];
		$page_hauteur = $formatarray['height'];
		$format = array($page_largeur, $page_hauteur);
		$marge_gauche = isset($conf->global->MAIN_PDF_MARGIN_LEFT) ? $conf->global->MAIN_PDF_MARGIN_LEFT : 10;
		$marge_droite = isset($conf->global->MAIN_PDF_MARGIN_RIGHT) ? $conf->global->MAIN_PDF_MARGIN_RIGHT : 10;
		$marge_haute = isset($conf->global->MAIN_PDF_MARGIN_TOP) ? $conf->global->MAIN_PDF_MARGIN_TOP : 10;
		$marge_basse = isset($conf->global->MAIN_PDF_MARGIN_BOTTOM) ? $conf->global->MAIN_PDF_MARGIN_BOTTOM : 10;

		// Create pdf instance
		$pdf = pdf_getInstance($format);
		$pdf->SetAutoPageBreak(0, 0);

		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));

		$pdf->Open();
		if (!empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) {
			$pdf->SetCompression(false);
		}
		$pdf->SetMargins($marge_gauche, $marge_haute, $marge_droite);   // Left, Top, Right

		$html = file_get_contents(dol_buildpath('/adhcnv/src/cerfa11580.html'));
		$html1stPage = explode('@breakpage@', $html)[0];
		$html2ndPage = explode('@breakpage@', $html)[1];

		require_once DOL_DOCUMENT_ROOT . '/adherents/class/adherent.class.php';

		$invoice = $parameters['object'];
		$result = $invoice->fetchObjectLinked();
		if ($result < 0) {
			setEventMessage('Impossible de trouvé les trucs liées à la facture');
		}
		foreach ($invoice->linkedObjects as $objId => $linkObjects) {
			$linkObject = reset($linkObjects);
			if (property_exists($linkObject, 'element') && $linkObject->element == 'subscription') {

				if (property_exists($linkObject, 'fk_adherent') && !empty($linkObject->fk_adherent)) {
					$adhId = $linkObject->fk_adherent;
					break;
				}
			}
		}
		if (!empty($adhId)) {

			$adh = new Adherent($this->db);
			$result = $adh->fetch($adhId);
			if ($result < 0) {
				setEventMessage($adh->error);
			} elseif ($adh->typeid == 1) {
				$concatpdffile = 'tmpadhcnv' . (empty($adhId) ? '' : '_' . $adhId);
				$file = $conf->adhcnv->dir_temp . '/' . $concatpdffile . '.pdf';
				dol_mkdir($conf->adhcnv->dir_temp);

				// New page
				$pdf->AddPage();

				$html1stPage = str_replace('$MYSOC_NOM$', $mysoc->name, $html1stPage);
				$html1stPage = str_replace('$MYSOC_ADRESSE$', $mysoc->address, $html1stPage);
				$html1stPage = str_replace('$MYSOC_CP$', $mysoc->zip, $html1stPage);
				$html1stPage = str_replace('$MYSOC_VILLE$', $mysoc->town, $html1stPage);
				$html1stPage = str_replace('$MYSOC_OBJECT$', $mysoc->object, $html1stPage);
				$html1stPage = str_replace('$IMG_CERFA1$', dol_buildpath('/adhcnv/src/cerfa11580_fichiers/logo_cerfa.png'), $html1stPage);
				$html1stPage = str_replace('$IMG_CHBK$', dol_buildpath('/adhcnv/src/cerfa11580_fichiers/chkbx.png'), $html1stPage);
				$html1stPage = str_replace('$IMG_CHBK_CHK$', dol_buildpath('/adhcnv/src/cerfa11580_fichiers/chkbx_chk.png'), $html1stPage);

				$pdf->writeHTMLCell($page_largeur - $marge_gauche - $marge_droite, $page_hauteur - $marge_haute - $marge_basse, $marge_gauche, $marge_haute, $html1stPage);

				$pdf->AddPage();


				$html2ndPage = str_replace('$NOM$', $adh->lastname, $html2ndPage);
				$html2ndPage = str_replace('$PRENOM$', $adh->firstname, $html2ndPage);
				$html2ndPage = str_replace('$ADRESSE$', $adh->address, $html2ndPage);
				$html2ndPage = str_replace('$CP$', $adh->zip, $html2ndPage);
				$html2ndPage = str_replace('$VILLE$', $adh->town, $html2ndPage);
				$html2ndPage = str_replace('$MONTANT$', price($adh->last_subscription_amount), $html2ndPage);
				$html2ndPage = str_replace('$MONTANTLETTRE$', $this->amountToLetters($adh->last_subscription_amount), $html2ndPage);
				$html2ndPage = str_replace('$DATEREGLEMENT$', dol_print_date($adh->last_subscription_date), $html2ndPage);
				$html2ndPage = str_replace('$DATEREGLEMENT$', '01/03/2019', $html2ndPage);

				$html2ndPage = str_replace('$IMG_CERFA_SIGN$', dol_buildpath('/adhcnv/src/cerfa11580_fichiers/cie_mini.png'), $html2ndPage);
				$html2ndPage = str_replace('$IMG_CHBK$', dol_buildpath('/adhcnv/src/cerfa11580_fichiers/chkbx.png'), $html2ndPage);
				$html2ndPage = str_replace('$IMG_CHBK_CHK$', dol_buildpath('/adhcnv/src/cerfa11580_fichiers/chkbx_chk.png'), $html2ndPage);
				//print $html2ndPage;
				//exit;
				$pdf->writeHTMLCell($page_largeur - $marge_gauche - $marge_droite, $page_hauteur - $marge_haute - $marge_basse, $marge_gauche, $marge_haute, $html2ndPage);

				$pdf->Close();

				$pdf->Output($file, 'F');

				unset($pdf);
				// Annexe file was generated

				$filetoconcat1 = array($parameters['file']);
				$filetoconcat2 = array($file);
				dol_syslog(get_class($this) . '::afterPDFCreation ' . $filetoconcat1 . ' - ' . $filetoconcat2);

				$filetoconcat = array_merge($filetoconcat1, $filetoconcat2);

				// Create empty PDF
				$pdf = pdf_getInstance($format);
				if (class_exists('TCPDF')) {
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));

				if ($conf->global->MAIN_DISABLE_PDF_COMPRESSION)
					$pdf->SetCompression(false);
				//$pdf->SetCompression(false);

				$pagecount = $this->concat($pdf, $filetoconcat);

				if ($pagecount) {
					$pdf->Output($filetoconcat1[0], 'F');
					if (!empty($conf->global->MAIN_UMASK)) {
						@chmod($file, octdec($conf->global->MAIN_UMASK));
					}
					if (!empty($deltemp)) {
						// Delete temp files
						foreach ($deltemp as $dirtemp) {
							dol_delete_dir_recursive($dirtemp);
						}
					}
				}
			}
		}

		return 0;
	}


	/**
	 * concat
	 * @param unknown_type $pdf Pdf
	 * @param unknown_type $files files
	 * @return void
	 */
	public function concat(&$pdf, $files)
	{
		foreach ($files as $file) {
			$pagecount = $pdf->setSourceFile($file);
			for ($i = 1; $i <= $pagecount; $i++) {
				$tplidx = $pdf->ImportPage($i);
				$s = $pdf->getTemplatesize($tplidx);
				$pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
				$pdf->useTemplate($tplidx);
			}
		}

		return $pagecount;
	}

	/**
	 * numbers to letters
	 *
	 * @param mixed $montant amount
	 * @param mixed $devise1 devise 1 ex: euro
	 * @param mixed $devise2 devise 2 ex: centimes
	 * @return string               amount in letters
	 */
	private function amountToLetters($montant, $devise1 = '', $devise2 = '')
	{
		$unite = array();
		$dix = array();
		$cent = array();
		if (empty($devise1)) $dev1 = 'euros';
		else $dev1 = $devise1;
		if (empty($devise2)) $dev2 = 'centimes';
		else $dev2 = $devise2;
		$valeur_entiere = intval($montant);
		$valeur_decimal = intval(round($montant - intval($montant), 2) * 100);
		$dix_c = intval($valeur_decimal % 100 / 10);
		$cent_c = intval($valeur_decimal % 1000 / 100);
		$unite[1] = $valeur_entiere % 10;
		$dix[1] = intval($valeur_entiere % 100 / 10);
		$cent[1] = intval($valeur_entiere % 1000 / 100);
		$unite[2] = intval($valeur_entiere % 10000 / 1000);
		$dix[2] = intval($valeur_entiere % 100000 / 10000);
		$cent[2] = intval($valeur_entiere % 1000000 / 100000);
		$unite[3] = intval($valeur_entiere % 10000000 / 1000000);
		$dix[3] = intval($valeur_entiere % 100000000 / 10000000);
		$cent[3] = intval($valeur_entiere % 1000000000 / 100000000);
		$chif = array('', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze',
					  'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix sept', 'dix huit', 'dix neuf');
		$secon_c = '';
		$trio_c = '';
		for ($i = 1; $i <= 3; $i++) {
			$prim[$i] = '';
			$secon[$i] = '';
			$trio[$i] = '';
			if ($dix[$i] == 0) {
				$secon[$i] = '';
				$prim[$i] = $chif[$unite[$i]];
			} elseif ($dix[$i] == 1) {
				$secon[$i] = '';
				$prim[$i] = $chif[($unite[$i] + 10)];
			} elseif ($dix[$i] == 2) {
				if ($unite[$i] == 1) {
					$secon[$i] = 'vingt et';
					$prim[$i] = $chif[$unite[$i]];
				} else {
					$secon[$i] = 'vingt';
					$prim[$i] = $chif[$unite[$i]];
				}
			} elseif ($dix[$i] == 3) {
				if ($unite[$i] == 1) {
					$secon[$i] = 'trente et';
					$prim[$i] = $chif[$unite[$i]];
				} else {
					$secon[$i] = 'trente';
					$prim[$i] = $chif[$unite[$i]];
				}
			} elseif ($dix[$i] == 4) {
				if ($unite[$i] == 1) {
					$secon[$i] = 'quarante et';
					$prim[$i] = $chif[$unite[$i]];
				} else {
					$secon[$i] = 'quarante';
					$prim[$i] = $chif[$unite[$i]];
				}
			} elseif ($dix[$i] == 5) {
				if ($unite[$i] == 1) {
					$secon[$i] = 'cinquante et';
					$prim[$i] = $chif[$unite[$i]];
				} else {
					$secon[$i] = 'cinquante';
					$prim[$i] = $chif[$unite[$i]];
				}
			} elseif ($dix[$i] == 6) {
				if ($unite[$i] == 1) {
					$secon[$i] = 'soixante et';
					$prim[$i] = $chif[$unite[$i]];
				} else {
					$secon[$i] = 'soixante';
					$prim[$i] = $chif[$unite[$i]];
				}
			} elseif ($dix[$i] == 7) {
				if ($unite[$i] == 1) {
					$secon[$i] = 'soixante et';
					$prim[$i] = $chif[$unite[$i] + 10];
				} else {
					$secon[$i] = 'soixante';
					$prim[$i] = $chif[$unite[$i] + 10];
				}
			} elseif ($dix[$i] == 8) {
				if ($unite[$i] == 1) {
					$secon[$i] = 'quatre-vingts et';
					$prim[$i] = $chif[$unite[$i]];
				} else {
					$secon[$i] = 'quatre-vingt';
					$prim[$i] = $chif[$unite[$i]];
				}
			} elseif ($dix[$i] == 9) {
				if ($unite[$i] == 1) {
					$secon[$i] = 'quatre-vingts et';
					$prim[$i] = $chif[$unite[$i] + 10];
				} else {
					$secon[$i] = 'quatre-vingts';
					$prim[$i] = $chif[$unite[$i] + 10];
				}
			}
			if ($cent[$i] == 1) $trio[$i] = 'cent';
			elseif ($cent[$i] != 0 || $cent[$i] != '') $trio[$i] = $chif[$cent[$i]] . ' cents';
		}


		$chif2 = array('', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix',
					   'quatre-vingts', 'quatre-vingts dix');
		$secon_c = $chif2[$dix_c];
		if ($cent_c == 1) $trio_c = 'cent';
		elseif ($cent_c != 0 || $cent_c != '') $trio_c = $chif[$cent_c] . ' cents';

		if (($cent[3] == 0 || $cent[3] == '') && ($dix[3] == 0 || $dix[3] == '') && ($unite[3] == 1))
			$somme = $trio[3] . '  ' . $secon[3] . ' ' . $prim[3] . ' million ';
		elseif (($cent[3] != 0 && $cent[3] != '') || ($dix[3] != 0 && $dix[3] != '') || ($unite[3] != 0 && $unite[3] != ''))
			$somme = $trio[3] . ' ' . $secon[3] . ' ' . $prim[3] . ' millions ';
		else
			$somme = $trio[3] . ' ' . $secon[3] . ' ' . $prim[3];

		if (($cent[2] == 0 || $cent[2] == '') && ($dix[2] == 0 || $dix[2] == '') && ($unite[2] == 1))
			$somme = $somme . ' mille ';
		elseif (($cent[2] != 0 && $cent[2] != '') || ($dix[2] != 0 && $dix[2] != '') || ($unite[2] != 0 && $unite[2] != ''))
			$somme = $somme . $trio[2] . ' ' . $secon[2] . ' ' . $prim[2] . ' milles ';
		else
			$somme = $somme . $trio[2] . ' ' . $secon[2] . ' ' . $prim[2];

		$somme = $somme . $trio[1] . ' ' . $secon[1] . ' ' . $prim[1];

		$somme = $somme . ' ' . $dev1 . ' ';

		if (($cent_c == '0' || $cent_c == '') && ($dix_c == '0' || $dix_c == ''))
			return $somme . ' et z&eacute;ro ' . $dev2;
		else
			return $somme . $trio_c . ' ' . $secon_c . ' ' . $dev2;
	}
}
