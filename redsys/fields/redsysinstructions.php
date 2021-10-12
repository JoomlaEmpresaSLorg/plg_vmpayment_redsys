<?php
/*
 *      TPVV Redsýs for Akeeba Subscriptions 3
 *      @package TPVV Redsýs for Akeeba Subscriptions 3
 *      @subpackage Content
 *      @author José António Cidre Bardelás
 *      @copyright Copyright (C) 2013-2016 José António Cidre Bardelás and Joomla Empresa. All rights reserved
 *      @license GNU/GPL v3 or later
 *      
 *      Contact us at info@joomlaempresa.com (http://www.joomlaempresa.es)
 *      
 *      This file is part of TPVV Redsýs for Akeeba Subscriptions 3.
 *      
 *          TPVV Redsýs for Akeeba Subscriptions 3 is free software: you can redistribute it and/or modify
 *          it under the terms of the GNU General Public License as published by
 *          the Free Software Foundation, either version 3 of the License, or
 *          (at your option) any later version.
 *      
 *          TPVV Redsýs for Akeeba Subscriptions 3 is distributed in the hope that it will be useful,
 *          but WITHOUT ANY WARRANTY; without even the implied warranty of
 *          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *          GNU General Public License for more details.
 *      
 *          You should have received a copy of the GNU General Public License
 *          along with TPVV Redsýs for Akeeba Subscriptions 3.  If not, see <http://www.gnu.org/licenses/>.
 */
if(!defined('_JEXEC'))
	die('Acesso a '.basename(__FILE__).' restrito.');


class JFormFieldRedsysinstructions extends JFormField {
	
	protected $type = 'redsysinstructions';

	function getInput() {
			$html = array();
			$html = JText::_($this->value);
			return $html;
		}
}
