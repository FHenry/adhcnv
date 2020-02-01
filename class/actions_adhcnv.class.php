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
			if ($massaction=='create_mailing') {
				require_once DOL_DOCUMENT_ROOT . '/comm/mailing/class/mailing.class.php';
				require_once DOL_DOCUMENT_ROOT . '/core/modules/mailings/modules_mailings.php';
				require_once DOL_DOCUMENT_ROOT . '/adherents/class/adherent.class.php';
				dol_include_once('/adhcnv/core/modules/adhcnv/adhcnv.modules.php');

				$adhIds = GETPOST('toselect', 'array');

				$error = 0;
				$adhMails = array();
				$cibles = array();

				$mailing = new Mailing($db);
				$mailing->titre = 'Communication CNV ' . dol_print_date(dol_now());
				$mailing->sujet = 'Communication CNV ' . dol_print_date(dol_now());
				$mailing->statut = 0;
				$mailing->email_from = $conf->global->MAIN_MAIL_SMTPS_ID;
				$mailing->email_errorsto = $conf->global->MAIN_MAIL_SMTPS_ID;
				$mailing->email_replyto = $conf->global->MAIN_MAIL_SMTPS_ID;

				$mailing->body='Bonjour&nbsp;__OTHER2__ __LASTNAME__ __FIRSTNAME__<br />';
				$mailing->body.='<br />';
				$mailing->body.='Ici mon texte<br />';
				$mailing->body.='<br />';
				$mailing->body.='<br />';
				$mailing->body.='__CHECK_READ__<br />';
				$mailing->body.='Pour vous d&eacute;sinscrire : __UNSUBSCRIBE__';

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
									'source_url'  => '<a href="'.DOL_URL_ROOT.'/adherents/card.php?rowid='.$adh->id.'">'.img_object('', "user").'</a>',
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
}
