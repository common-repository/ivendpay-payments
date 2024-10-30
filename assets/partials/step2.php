<div class="pop pop-choose-currency">
    <div id="step-2">
        <br>
        <fieldset>
            <h2><strong>Choose option</strong></h2>

            <?php if (count($listCoins) >= 6): ?>
            <div id="search-crypto-ivendpay-div">
              <input id="search-crypto-ivendpay" placeholder="Search" type="text" autocomplete="off" />
            </div>
            <?php else: ?>
            <br />
            <?php endif; ?>

            <div id="listCoins" class="form__radios">
                <?php foreach($listCoins as $key => $coin): ?>
                    <?php if ($coin['id'] != 'bpay-usdt'): ?>
                        <div class="form__radio <?php if ($key >= 6) echo 'hidden-coins' ?>" <?php if ($key >= 6) echo 'style="display: none"' ?>>
                            <label for="<?php echo esc_html($coin['id']) ?>">
                                <img src="<?php echo esc_url($coin['image']) ?>"/>
                                <span><?php echo esc_html($coin['name']) ?> (<?php echo esc_html($coin['ticker_name']) ?>)</span>
                            </label>
                            <input id="<?php echo esc_html($coin['id']) ?>" name="crypto-method" type="radio" value="<?php echo esc_html($coin['id']) ?>" />
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php foreach($listCoins as $key => $coin): ?>
                    <?php if ($coin['id'] == 'bpay-usdt'): ?>
                        <?php if (count($listCoins) > 1): ?>
                            <h2 class="or-pay-with"><strong>or pay with</strong></h2>
                        <?php endif; ?>
                        <div class="form__radio bpay">
                            <label for="<?php echo esc_html($coin['id']) ?>">
                                <img src="<?php echo esc_url($coin['image']) ?>"/>
                                <span><?php echo esc_html($coin['name']) ?></span>
                            </label>
                            <input id="<?php echo esc_html($coin['id']) ?>" name="crypto-method" type="radio" value="<?php echo esc_html($coin['id']) ?>" />
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <?php if (count($listCoins) >= 6): ?>
        <br>
        <a href="javascript:void(0)" class="button button--full showMoreCoinsIvendPay" data-action="show">Show More</a>
        <a href="javascript:void(0)" style="display: none" class="button button--full showMoreCoinsIvendPay">Hide</a>
      <?php endif; ?>
    </div>
</div>
