<?php
/*
 *      TPVV Redsýs for VirtueMart 2
 *      @package TPVV Redsýs for VirtueMart 2
 *      @subpackage Content
 *      @author José António Cidre Bardelás
 *      @copyright Copyright (C) 2012-2016 José António Cidre Bardelás and Joomla Empresa. All rights reserved
 *      @license GNU/GPL v3 or later
 *      
 *      Contact us at info@joomlaempresa.com (http://www.joomlaempresa.es)
 *      
 *      This file is part of TPVV Redsýs for VirtueMart 2.
 *      
 *          TPVV Redsýs for VirtueMart 2 is free software: you can redistribute it and/or modify
 *          it under the terms of the GNU General Public License as published by
 *          the Free Software Foundation, either version 3 of the License, or
 *          (at your option) any later version.
 *      
 *          TPVV Redsýs for VirtueMart 2 is distributed in the hope that it will be useful,
 *          but WITHOUT ANY WARRANTY; without even the implied warranty of
 *          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *          GNU General Public License for more details.
 *      
 *          You should have received a copy of the GNU General Public License
 *          along with TPVV Redsýs for VirtueMart 2.  If not, see <http://www.gnu.org/licenses/>.
 */
if(!defined('_VALID_MOS') && !defined('_JEXEC')) 
	die('Acesso a '.basename(__FILE__).' restrito.');
?>
		<!-- Response page static content begin -->
		<img src="<?php echo $viewData['imgSrc']; ?>" alt="<?php echo $viewData['imgAlt']; ?>" border="0" />
		<h1><?php echo $viewData['title']; ?></h1>
		<p><?php echo $viewData['text']; ?></p>
		<p style="color: red;"><?php echo JText::_('VMPAYMENT_REDSYS_RESPONSE_ERROR_MESSAGE').' '.$viewData['errorText']; ?></p>
		<!-- Response page static content end -->
		<?php echo JHTML::_('link', $viewData['linkOrder'], JText::_('VMPAYMENT_REDSYS_CONSULTA_ENCOMENDA')); ?>