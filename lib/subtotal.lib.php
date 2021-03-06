<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
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
 *	\file		lib/importdevis.lib.php
 *	\ingroup	importdevis
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function subtotalAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("subtotal@subtotal");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/subtotal/admin/subtotal_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/subtotal/admin/subtotal_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, $object, $head, $h, 'subtotal', $showLabel=false);

    return $head;
}

function getHtmlSelectTitle(&$object, $showLabel=false)
{
	global $langs;
	
	dol_include_once('/subtotal/class/subtotal.class.php');
	$TTitle = TSubtotal::getAllTitleFromDocument($object);
	$html = '';
	if ($showLabel) $html.= '<label for="under_title">'.$langs->trans('subtotalLabelForUnderTitle').'</label>';
	$html.= '<select onChange="$(\'select[name=under_title]\').val(this.value);" name="under_title" class="under_title minwidth200"><option value="-1"></option>';
	
	$nbsp = '&nbsp;';
	foreach ($TTitle as &$line)
	{
		$str = str_repeat($nbsp, ($line->qty - 1) * 3);
		$html .= '<option value="'.$line->rang.'">'.$str.(!empty($line->label) ? $line->label : $line->desc).'</option>';
	}
	
	$html .= '</select>';
	return $html;
}

function getTFreeText()
{
	global $db,$conf;
	
	$TFreeText = array();
	
	$sql = 'SELECT rowid, label, content, active, entity FROM '.MAIN_DB_PREFIX.'c_subtotal_free_text WHERE active = 1 AND entity = '.$conf->entity.' ORDER BY label';
	$resql = $db->query($sql);
	
	if ($resql)
	{
		while ($row = $db->fetch_object($resql))
		{
			$TFreeText[$row->rowid] = $row;
		}
	}
	
	return $TFreeText;
}

function getHtmlSelectFreeText($withEmpty=true)
{
	global $langs;
	
	$TFreeText = getTFreeText();
	$html = '<label for="free_text">'.$langs->trans('subtotalLabelForFreeText').'</label>';
	$html.= '<select onChange="getTFreeText($(this));" name="free_text" class="minwidth200">';
	if ($withEmpty) $html.= '<option value=""></option>';

	$TFreeTextContents = array();
	foreach ($TFreeText as $id => $tab)
	{
		$html.= '<option value="'.$id.'">'.$tab->label.'</option>';
		$TFreeTextContents[$id] = $tab->content;
	}

	$html .= '</select>';

	$html .= '<script type="text/javascript">';
	$html .= 'function getTFreeText(select) {';
	$html .= ' var TFreeText = '.json_encode($TFreeTextContents).';';
	$html .= ' var id = select.val();';
	$html .= ' if (id in TFreeText) {';
	$html .= '  var content = TFreeText[id];';
	$html .= '  if (typeof CKEDITOR == "object" && typeof CKEDITOR.instances != "undefined" && "sub-total-title" in CKEDITOR.instances) {';
	$html .= '   var editor = CKEDITOR.instances["sub-total-title"];';
	$html .= '   editor.setData(content);';
	$html .= '  } else {';
	$html .= '   $("#sub-total-title").val(content);';
	$html .= '  }';
	$html .= ' }';
	$html .= '}';
	$html .= '</script>';

	return $html;
}

function _updateSubtotalLine(&$object, &$line)
{
	$label = GETPOST('line-title');
	$description = ($line->qty>90) ? '' : GETPOST('line-description');
	$pagebreak = (int) GETPOST('line-pagebreak');

	$level = GETPOST('subtotal_level', 'int');
	if (!empty($level))
	{
		if ($line->qty > 90) $line->qty = 100 - $level; // Si on edit une ligne sous-total
		else $line->qty = $level;
	}
	
	$res = TSubtotal::doUpdateLine($object, $line->id, $description, 0, $line->qty, 0, '', '', 0, 9, 0, 0, 'HT', $pagebreak, 0, 1, null, 0, $label, TSubtotal::$module_number, $line->array_options);

	return $res;
}

function _updateSubtotalBloc($object, $line)
{
	global $conf,$langs;
	
	$subtotal_tva_tx = GETPOST('subtotal_tva_tx', 'int');
	$subtotal_progress = GETPOST('subtotal_progress', 'int');
	$array_options = $line->array_options;
	$showBlockExtrafields = GETPOST('showBlockExtrafields');
	
	if ($subtotal_tva_tx != '' || $subtotal_progress != '' || (!empty($showBlockExtrafields) && !empty($array_options)))
	{
		$error_progress = $nb_progress_update = $nb_progress_not_updated = 0;
		$TLine = TSubtotal::getLinesFromTitleId($object, $line->id);
		foreach ($TLine as &$line)
		{
			if (!TSubtotal::isTitle($line) && !TSubtotal::isSubtotal($line))
			{
				if (!empty($showBlockExtrafields)) $line->array_options = $array_options;
				if ($subtotal_tva_tx == '') $subtotal_tva_tx = $line->tva_tx;
				if ($object->element == 'facture' && !empty($conf->global->INVOICE_USE_SITUATION) && $object->type == Facture::TYPE_SITUATION)
				{
					if ($subtotal_progress == '') $subtotal_progress = $line->situation_percent;
					else
					{
						$prev_percent = $line->get_prev_progress($object->id);
						if ($subtotal_progress < $prev_percent)
						{
							$nb_progress_not_updated++;
							$subtotal_progress = $line->situation_percent;
						}
					}
				}
				
				$res = TSubtotal::doUpdateLine($object, $line->id, $line->desc, $line->subprice, $line->qty, $line->remise_percent, $line->date_start, $line->date_end, $subtotal_tva_tx, $line->product_type, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->fk_parent_line, $line->skip_update_total, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $subtotal_progress, $line->fk_unit);

				if ($res > 0) $success_updated_line++;
				else $error_updated_line++;
			}
		}

		if ($nb_progress_not_updated > 0) setEventMessage($langs->trans('subtotal_nb_progress_not_updated', $nb_progress_not_updated), 'warnings');
		
		if ($success_updated_line > 0) setEventMessage($langs->trans('subtotal_success_updated_line', $success_updated_line));
		if ($error_updated_line > 0)
		{
			setEventMessage($langs->trans('subtotal_error_updated_line', $error_updated_line), 'errors');
			return -$error_updated_line;
		}
		
		return $success_updated_line;
	}
	
	return 0;
}

function _createExtraComprisNonCompris()
{
	global $db;
	
	dol_include_once('/core/class/extrafields.class.php');
	
	$extra = new ExtraFields($db); // propaldet, commandedet, facturedet
	$extra->addExtraField('subtotal_nc', 'Non compris', 'varchar', 0, 255, 'propaldet', 0, 0, '', unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'), 0, '', 0, 1);
	$extra->addExtraField('subtotal_nc', 'Non compris', 'varchar', 0, 255, 'commandedet', 0, 0, '', unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'), 0, '', 0, 1);
	$extra->addExtraField('subtotal_nc', 'Non compris', 'varchar', 0, 255, 'facturedet', 0, 0, '', unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'), 0, '', 0, 1);
}