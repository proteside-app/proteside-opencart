<?php
// Heading
$_['heading_title']           = 'Proteside checkout protection';

// Text
$_['text_extension']          = 'Extensions';
$_['text_description']        = 'Loads the Proteside client-side protection (PCI DSS 4.0 requirements 6.4.3 and 11.6.1) on your payment pages using your public SDK key.';
$_['text_edit']               = 'Settings';
$_['text_success']            = 'Settings saved.';
$_['text_success_validated']  = 'Settings saved and status refreshed.';
$_['text_status_refreshed']   = 'Status refreshed.';
$_['text_section_activation'] = 'Activation';
$_['text_section_status']     = 'Connection status';
$_['text_section_pages']      = 'Where to load the protection';
$_['text_section_options']    = 'Options';
$_['text_section_advanced']   = 'Advanced';
$_['text_section_diagnostics'] = 'Diagnostics';
$_['text_default_store']      = 'Default store';
$_['text_store_settings']     = 'Settings for';
$_['text_store_inherits']     = 'This store has no settings of its own yet and uses the ones of the default store. Saving here creates settings for this store only.';
$_['text_yes']                = 'Yes';
$_['text_no']                 = 'No';
$_['text_every_hours']        = 'Every %d hours';
$_['text_channel_auto']       = 'Follow the dashboard (recommended)';
$_['text_channel_stable']     = 'stable';
$_['text_channel_latest']     = 'latest';
$_['text_no_account']         = "Don't have an account yet?";
$_['text_docs']               = 'Documentation';
$_['text_copied']             = 'Copied';
$_['text_checking']           = 'Checking…';
$_['text_never']              = 'never';
$_['text_less_minute']        = 'less than a minute';
$_['text_minutes']            = '%d min';
$_['text_hours']              = '%d h';
$_['text_days']               = '%d d';

// Status
$_['status_active']           = 'Active';
$_['status_grace']            = 'Grace period';
$_['status_suspended']        = 'Suspended';
$_['status_inactive']         = 'Inactive';
$_['status_invalid']          = 'Invalid key';
$_['status_connection_error'] = 'Connection error';
$_['status_unknown']          = 'Not verified yet';
$_['text_plan']               = 'Plan: %s';
$_['text_checked_ago']        = 'Checked %s ago';
$_['text_decision']           = 'Protection on this store:';
$_['text_domain_mismatch']    = 'This key belongs to a store registered under a different domain. Protection still loads, but check the store in your dashboard.';
$_['text_notice_grace']       = 'Your Proteside subscription is in its grace period. Update your billing to keep the protection active.';
$_['text_notice_invalid']     = 'Your Proteside SDK key is invalid, so the protection is not being loaded.';
$_['text_notice_connection']  = 'Proteside could not be reached (%s). The last known status is kept for up to 48 hours.';
$_['text_notice_disabled']    = 'The protection is disabled in the settings below.';
$_['text_notice_no_key']      = 'Enter your SDK key to activate the protection.';
$_['text_notice_suspended']   = 'Your Proteside subscription is not active, so the protection is not being loaded. Open the dashboard to review your plan.';

// Entry
$_['entry_store']             = 'Store';
$_['entry_status']            = 'Enable protection';
$_['entry_sdk_key']           = 'SDK key';
$_['entry_checkout']          = 'Checkout (all steps)';
$_['entry_order_confirmation'] = 'Order confirmation (thank you)';
$_['entry_cart']              = 'Cart';
$_['entry_shop']              = 'Shop and product pages';
$_['entry_account']           = 'My account';
$_['entry_login']             = 'Login page';
$_['entry_all_store']         = 'Entire store';
$_['entry_custom_urls']       = 'Custom URL patterns (one per line, * as wildcard)';
$_['entry_channel']           = 'Release channel';
$_['entry_calibration']       = 'Calibration (seconds)';
$_['entry_inline_rules']      = 'Embed blocking rules in the page (inline config)';
$_['entry_interval']          = 'Re-check status every';
$_['entry_deferred']          = 'Re-check after storefront visits (no cron needed)';

// Help
$_['help_sdk_key']            = 'Paste the public key from your Proteside dashboard (Settings → Pages and Domains). Format: pk_live_ followed by 32 hex characters. It is public by design.';
$_['help_pages']              = 'Checkout includes every step of the order process, one-page checkout extensions and the pages of your payment extensions. Order confirmation is the success page, which the SDK uses to confirm card payments.';
$_['help_custom_urls']        = 'Matched against the request path and URL, e.g. /finalizar-compra or *route=extension/payment/mypayment*.';
$_['help_calibration']        = 'Only used when the remote configuration cannot be fetched. Normally the value from the dashboard applies.';
$_['help_inline_rules']       = 'Copies your active rules and allowed GTM containers from the dashboard into the bootstrapper so blocking starts from the first byte. Refreshed on every status check.';
$_['help_deferred']           = 'When the interval has elapsed, the next storefront visit schedules a status check that runs after the page has been sent to the visitor. Turn it off if you call the cron URL from your server.';

// Diagnostics
$_['diag_extension_version']  = 'Extension version';
$_['diag_bootstrapper_version'] = 'Bundled bootstrapper version';
$_['diag_method']             = 'Injection method';
$_['diag_method_value']       = 'header view event, right after the opening head tag';
$_['diag_channel']            = 'Channel in use';
$_['diag_inline_rules_count'] = 'Inline rules / GTM containers';
$_['diag_csp_hash']           = 'CSP hash of the inline script';
$_['help_csp_hash']           = 'Only needed if your store sends a Content-Security-Policy header. Add it to script-src together with https://cdn.proteside.com, and allow https://app.proteside.com and https://cdn.proteside.com in connect-src. It changes whenever your rules or GTM containers change.';
$_['diag_cron_url']           = 'Cron URL (optional)';
$_['help_cron_url']           = 'Call this URL from your server scheduler (e.g. every hour) to refresh the status of every store without relying on storefront visits. Keep it private.';
$_['diag_cron_native']        = 'The OpenCart cron job "proteside" (Extensions → Cron Jobs) also refreshes the status every hour when the server runs cron.php.';
$_['diag_verify']             = 'Verify the installation';
$_['diag_verify_hint']        = 'Open your checkout and run Proteside.getStatus() in the browser console. installationOrderValid must be true.';
$_['diag_probe_help']         = 'Renders the storefront header of this store and checks that the Proteside snippet is the first script after the opening head tag.';

// Probe results
$_['probe_ok']                = 'The Proteside snippet is the first script after the opening head tag of this store.';
$_['probe_not_first']         = 'The snippet is injected, but %d script(s) come before it in the head. Another extension or the theme prints them before the header template; the dashboard will show an installation-order warning.';
$_['probe_no_head']           = 'The header template of the active theme has no opening head tag, so the snippet cannot be injected. Contact support.';
$_['probe_not_injected']      = 'The snippet was not injected. Check that the event "proteside" is enabled in Extensions → Events.';
$_['probe_error']             = 'The storefront could not be reached (%s).';
$_['probe_no_key']            = 'Save a valid SDK key first.';

// Button
$_['button_save']             = 'Save changes';
$_['button_check_status']     = 'Check status';
$_['button_open_dashboard']   = 'Open Proteside dashboard';
$_['button_get_proteside']    = 'Get Proteside';
$_['button_copy']             = 'Copy';
$_['button_probe']            = 'Test the installation';

// Error
$_['error_permission']        = 'You do not have permission to do this.';
$_['error_sdk_key']           = 'The SDK key format is invalid. Expected pk_live_ or pk_test_ followed by 32 hex characters.';
$_['error_calibration']       = 'Calibration must be empty or between 10 and 120 seconds.';
$_['error_form']              = 'Please check the form for errors.';
$_['error_library']           = 'The Proteside library files are missing. Reinstall the extension.';

// Decision
$_['diag_decision_active']    = 'active — the protection loads on the selected pages.';
$_['diag_decision_inactive']  = 'inactive — the protection does NOT load.';
