<?php /* Smarty version Smarty-3.1.19, created on 2018-08-16 01:55:43
         compiled from "/var/www/html/prestashop/admin808n4f6xa/themes/default/template/content.tpl" */ ?>
<?php /*%%SmartyHeaderCode:846652275b7503cfaf3533-84366224%%*/if(!defined('SMARTY_DIR')) exit('no direct access allowed');
$_valid = $_smarty_tpl->decodeProperties(array (
  'file_dependency' => 
  array (
    '7f39fe2dcd9c162b84f35b79bef6e0e928b89d04' => 
    array (
      0 => '/var/www/html/prestashop/admin808n4f6xa/themes/default/template/content.tpl',
      1 => 1495464440,
      2 => 'file',
    ),
  ),
  'nocache_hash' => '846652275b7503cfaf3533-84366224',
  'function' => 
  array (
  ),
  'variables' => 
  array (
    'content' => 0,
  ),
  'has_nocache_code' => false,
  'version' => 'Smarty-3.1.19',
  'unifunc' => 'content_5b7503cfb04f38_73408334',
),false); /*/%%SmartyHeaderCode%%*/?>
<?php if ($_valid && !is_callable('content_5b7503cfb04f38_73408334')) {function content_5b7503cfb04f38_73408334($_smarty_tpl) {?>
<div id="ajax_confirmation" class="alert alert-success hide"></div>

<div id="ajaxBox" style="display:none"></div>


<div class="row">
	<div class="col-lg-12">
		<?php if (isset($_smarty_tpl->tpl_vars['content']->value)) {?>
			<?php echo $_smarty_tpl->tpl_vars['content']->value;?>

		<?php }?>
	</div>
</div>
<?php }} ?>
