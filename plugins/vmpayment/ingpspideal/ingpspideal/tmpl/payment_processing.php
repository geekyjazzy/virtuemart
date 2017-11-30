<?php
defined ('_JEXEC') or die();

?>

<div class="virtuemart_ginger_end" id="virtuemart_ginger_end">
    <span id="virtuemart_ginger_end_message" class="virtuemart_ginger_end_message">
        <p><?php echo $viewData['description']. 
                "<p>". JText::_("PLG_VMPAYMENT_INGPSPIDEAL_PAYMENT_BEING_PROCESSED"). "</p>"; ?></p>
    </span>
    <span id="virtuemart_ginger_end_spinner" class="virtuemart_ginger_end_spinner">
        <img src="<?php echo $viewData['logo']; ?>"/>
    </span>
</div>

 
 