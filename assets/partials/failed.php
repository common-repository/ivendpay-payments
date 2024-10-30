<div class="pop">
	<form id="appendSteps" class="form">
        <div class="bxc-failed-cnt bxc-box" style="display: block">
            <lottie-player src="<?php echo esc_url($siteUrl) ?>expire.json"  background="transparent"  speed="1"  style="width: 150px; height: 150px; margin: 0 auto;" autoplay></lottie-player>
            <div class="bxc-title">No payment</div>
            <div class="bxc-text">We didn't detect a payment. If you have already paid, please contact us.</div>
            <a href="<?php echo esc_url($cancelOrder) ?>" class="button button--full">Back</a>
        </div>
	</form>
</div>
