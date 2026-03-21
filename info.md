## Information

# Developer
```js
var conference_id = 108;
var workshop_id = 108;
var virtual_id = 108;
var workshop_conference_id = 108;
var workshop_virtual_id = 108;
```

# Production
```javascript
var conference_id = 6623;
var workshop_id = 6647;
var virtual_id = 6625;
var workshop_conference_id = 12670;
var workshop_virtual_id = 12672;
```

# Payment 
```php
<div>
                    <label for="cison_exam_payment_platform">Payment Platform <span>*</span></label>
                    <select id="cison_exam_payment_platform" name="payment_platform" required>
                        <option value="">Select</option>
                        <?php foreach ($payment_platforms as $platform_value => $platform_label): ?>
                            <option value="<?php echo esc_attr($platform_value); ?>" <?php selected($values['payment_platform'], $platform_value); ?>>
                                <?php echo esc_html($platform_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="cison-exam-registration__help">Payment processing remains on WooCommerce.</small>
                </div>
            </div>
```