<?php
/*
 *      TPVV Redsýs for VirtueMart 3
 *      @package TPVV Redsýs for VirtueMart 3
 *      @subpackage Content
 *      @author José António Cidre Bardelás
 *      @copyright Copyright (C) 2012-2016 José António Cidre Bardelás and Joomla Empresa. All rights reserved
 *      @license GNU/GPL v3 or later
 *      
 *      Contact us at info@joomlaempresa.com (http://www.joomlaempresa.es)
 *      
 *      This file is part of TPVV Redsýs for VirtueMart 3.
 *      
 *          TPVV Redsýs for VirtueMart 3 is free software: you can redistribute it and/or modify
 *          it under the terms of the GNU General Public License as published by
 *          the Free Software Foundation, either version 3 of the License, or
 *          (at your option) any later version.
 *      
 *          TPVV Redsýs for VirtueMart 3 is distributed in the hope that it will be useful,
 *          but WITHOUT ANY WARRANTY; without even the implied warranty of
 *          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *          GNU General Public License for more details.
 *      
 *          You should have received a copy of the GNU General Public License
 *          along with TPVV Redsýs for VirtueMart 3.  If not, see <http://www.gnu.org/licenses/>.
 */
if(!defined('_JEXEC'))
	die('Acesso a '.basename(__FILE__).' restrito.');

class JFormFieldRedsyschave extends JFormField {
	
	protected $type = 'redsyschave';

	function getInput() {
		$idPagamento = JFactory::getApplication()->input->get('cid', 0, 'ARRAY');
		// $idPagamento = $idPagamento[0];
		// JComponentHelper::isInstalled('com_jetpvvcommon')
		$component = JComponentHelper::getComponent('com_jetpvvcommon', true);
		if(!file_exists(JPATH_ADMINISTRATOR.'/components/com_jetpvvcommon/version.php')) {
			$size = ( isset($this->size) ? 'size="'.$this->size.'"' : '' );
			return '<span class="label label-important">' . JText::_('VMPAYMENT_REDSYS_AVISO_CIFRADO') . '</span>';
		}
		elseif(!$component->enabled) {
			$size = ( isset($this->size) ? 'size="'.$this->size.'"' : '' );
			return '<span class="label label-important">' . JText::_('VMPAYMENT_REDSYS_AVISO_CIFRADO') . '</span>';
		}
		else {
			require_once JPATH_ADMINISTRATOR . '/components/com_jetpvvcommon/version.php';
			if(version_compare(JETPVVCOMMON_VERSION, '3.0.0', 'lt')) {
				$size = ( isset($this->size) ? 'size="'.$this->size.'"' : '' );
				return '<span class="label label-important">' . JText::_('VMPAYMENT_REDSYS_AVISO_CIFRADO') . '</span>';
			}
			// Load the modal behavior script.
			JHtml::_('behavior.modal', 'a.modal');

			// Setup variables for display.
			$html = array();
			$jeTPVVToken = version_compare(JVERSION, '3.0.0','ge') ? JSession::getFormToken() : JUtility::getToken();
			$link = 'index.php?option=com_jetpvvcommon&amp;layout=modal&amp;tmpl=component&amp;key='.$this->fieldname.'&amp;cid='.$idPagamento[0].'&amp;'.$jeTPVVToken.'=1';

			// The user select button.
			$html[] = '<div class="button2-left">';
			$html[] = '  <div class="blank">';
			$html[] = '	<a class="modal btn btn-primary" title="'.JText::_('VMPAYMENT_REDSYS_MUDAR_CHAVE_DET').'"  href="'.$link.'" rel="{handler: \'iframe\', size: {x: 800, y: 450}}">'.JText::_('VMPAYMENT_REDSYS_MUDAR_CHAVE').'</a>';
			$html[] = '  </div>';
			$html[] = '</div>';
			
			return implode("\n", $html);
		}
	}
}
