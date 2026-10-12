<div class="empty-content inboxzero" role="status">
    {{-- The translated animal name gives the joke context without seeing the image (WCAG 1.1.1).
         A polite status region does not move focus when the inbox becomes empty (WCAG 4.1.3). --}}
    <img class="inboxzero-animal" src="{{ asset(Module::getPublicPath('inboxzero').'/images/animals/'.$inboxZeroAnimal.'.png') }}" alt="{{ __('inboxzero::messages.animals.'.$inboxZeroAnimal.'.name') }}" width="180" height="160">
    <p class="inboxzero-message">{{ __('inboxzero::messages.animals.'.$inboxZeroAnimal.'.message') }}</p>
    <p class="inboxzero-status">{{ __('inboxzero::messages.empty') }}</p>
</div>
