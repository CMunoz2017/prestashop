<?php /* Smarty version Smarty-3.1.19, created on 2018-08-16 01:55:43
         compiled from "/var/www/html/prestashop/modules/welcome/views/templates/tooltip.tpl" */ ?>
<?php /*%%SmartyHeaderCode:2378888165b7503cfdaa762-78769241%%*/if(!defined('SMARTY_DIR')) exit('no direct access allowed');
$_valid = $_smarty_tpl->decodeProperties(array (
  'file_dependency' => 
  array (
    'af32c4d7aba010e84525518c981eeb9e693ea0bf' => 
    array (
      0 => '/var/www/html/prestashop/modules/welcome/views/templates/tooltip.tpl',
      1 => 1495464570,
      2 => 'file',
    ),
  ),
  'nocache_hash' => '2378888165b7503cfdaa762-78769241',
  'function' => 
  array (
  ),
  'has_nocache_code' => false,
  'version' => 'Smarty-3.1.19',
  'unifunc' => 'content_5b7503cfdb02e1_40880959',
),false); /*/%%SmartyHeaderCode%%*/?>
<?php if ($_valid && !is_callable('content_5b7503cfdb02e1_40880959')) {function content_5b7503cfdb02e1_40880959($_smarty_tpl) {?>

<div class="onboarding-tooltip">
  <div class="content"></div>
  <div class="onboarding-tooltipsteps">
    <div class="total"><?php echo smartyTranslate(array('s'=>'Step','d'=>'Modules.Welcome.Admin'),$_smarty_tpl);?>
 <span class="count">1/5</span></div>
    <div class="bulls">
    </div>
  </div>
  <button class="btn btn-primary btn-xs onboarding-button-next"><?php echo smartyTranslate(array('s'=>'Next','d'=>'Modules.Welcome.Admin'),$_smarty_tpl);?>
</button>
</div>
<?php }} ?>
