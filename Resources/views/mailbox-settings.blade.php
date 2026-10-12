<div class="form-group">
    <label class="col-sm-2 control-label" for="inboxzero-enabled">InboxZero</label>
    <div class="col-sm-6">
        <input type="hidden" name="inboxzero_enabled" value="0">
        <div class="checkbox inboxzero-mailbox-checkbox">
            <label>
                <input type="checkbox" id="inboxzero-enabled" name="inboxzero_enabled" value="1" aria-describedby="inboxzero-help" @if (old('inboxzero_enabled', $mailbox->getMeta('inboxzero_enabled', false))) checked @endif>
                {{ __('inboxzero::messages.enable_mailbox') }}
            </label>
        </div>
        <p class="help-block" id="inboxzero-help">{{ __('inboxzero::messages.enable_mailbox_help') }}</p>
    </div>
</div>
