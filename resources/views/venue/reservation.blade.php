<section class="hub-content wizard-panel stage-panel-right @container" aria-label="{{ $wizard['title'] }}">
    <a href="{{ route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_hub'] }}" class="panel-close" aria-label="{{ $hub['back'] }}"><span aria-hidden="true">×</span></a>
    <p class="hub-eyebrow">{{ $hub['phase'] }} {{ $phase }} · {{ $venue->name }}</p>
    <h2>{{ $wizard['title'] }}</h2>

    <div class="wizard" data-wizard
        data-quote-url="{{ route('reservation.quote', $venue->slug) }}"
        data-availability-url="{{ route('reservation.availability', $venue->slug) }}"
        data-submit-url="{{ route('reservation.store', $venue->slug) }}"
        data-venue-slug="{{ $venue->slug }}"
        data-locale="{{ $locale }}"
        data-currency="{{ $venue->currency }}"
        data-today="{{ $today }}"
        data-max-days="{{ \App\Services\Reservation\ReservationService::MAX_DAYS }}"
        data-preselect-space="{{ $preselectedSpace ?? '' }}"
    >
        <div data-wizard-flow>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-stone-600">{{ $wizard['intro'] }}</p>

            <div class="wizard-progress" aria-label="{{ $wizard['title'] }}">
                <p class="wizard-step-label" data-wizard-step-label aria-live="polite"></p>
                <ol>
                    @foreach ($wizard['steps'] as $stepLabel)
                        <li data-progress-step><span>{{ $stepLabel }}</span></li>
                    @endforeach
                </ol>
            </div>

            <form data-wizard-form novalidate autocomplete="on">
                <div data-step="1" class="wizard-step wizard-step-wide">
                    <input type="hidden" name="start_date" required>
                    <input type="hidden" name="end_date" required>

                    <div class="wizard-dates" data-date-picker>
                        <div class="wizard-slots">
                            <div class="wizard-slot" data-slot="start_date"><span>{{ $wizard['start_date'] }}</span><strong data-slot-value>{{ $wizard['cal_choose'] }}</strong></div>
                            <div class="wizard-slot" data-slot="end_date"><span>{{ $wizard['end_date'] }}</span><strong data-slot-value>{{ $wizard['cal_choose'] }}</strong></div>
                        </div>
                        <div class="wizard-cal">
                            <div class="wizard-cal-head">
                                <button type="button" data-cal-prev aria-label="{{ $wizard['cal_prev'] }}">←</button>
                                <strong data-cal-title aria-live="polite"></strong>
                                <button type="button" data-cal-next aria-label="{{ $wizard['cal_next'] }}">→</button>
                            </div>
                            <div class="wizard-cal-week" data-cal-weekdays aria-hidden="true"></div>
                            <div class="wizard-cal-grid" data-cal-grid></div>
                        </div>
                        <div class="wizard-dates-extra">
                            <p class="wizard-hint" data-days-hint aria-live="polite"></p>
                            @if ($wizard['multiday_hint'])
                                <p class="wizard-multiday"><span aria-hidden="true">%</span>{{ $wizard['multiday_hint'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div data-step="2" class="wizard-step" hidden>
                    <label class="wizard-field">
                        <span>{{ $wizard['candidates'] }}</span>
                        <input type="number" name="candidates" min="1" max="5000" value="50" inputmode="numeric" required>
                    </label>
                    <label class="wizard-field">
                        <span>{{ $wizard['recruiters'] }}</span>
                        <input type="number" name="recruiters" min="0" max="500" value="5" inputmode="numeric">
                    </label>
                    <label class="wizard-field">
                        <span>{{ $wizard['spaces'] }}</span>
                        <input type="number" name="spaces" min="1" max="10" value="1" inputmode="numeric" required>
                    </label>
                </div>

                <div data-step="3" class="wizard-step wizard-step-wide" hidden>
                    <fieldset>
                        <legend>{{ $wizard['choose_space'] }}</legend>
                        <div class="wizard-spaces">
                            @foreach ($spaceTypes as $spaceType)
                                <label class="wizard-space" data-space-option
                                    data-name="{{ $spaceType->translatedName($locale) }}"
                                    data-max-candidates="{{ $spaceType->max_candidates }}"
                                    data-max-capacity="{{ $spaceType->maxCapacity() }}"
                                    data-tech-crew="{{ $spaceType->tech_crew_available ? 1 : 0 }}"
                                >
                                    <input type="radio" name="space_type_slug" value="{{ $spaceType->slug }}">
                                    <span class="wizard-space-body">
                                        <strong>{{ $spaceType->translatedName($locale) }}</strong>
                                        <span>{{ str_replace(':count', (string) $spaceType->maxCapacity(), $wizard['fits']) }}</span>
                                        <span class="wizard-space-price">{{ $labels['from'] }} {{ $venue->currency }} {{ number_format((float) $spaceType->base_price, 0, ',', '.') }} {{ $labels['per_day'] }}</span>
                                        <span class="wizard-space-hint" data-space-hint></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="wizard-check" data-tech-crew-field hidden>
                        <input type="checkbox" name="tech_crew" value="1">
                        <span>{{ $wizard['tech_crew'] }}</span>
                    </label>
                </div>

                <div data-step="4" class="wizard-step" hidden>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['name'] }}</span>
                        <input type="text" name="guest_name" maxlength="100" autocomplete="name" required>
                    </label>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['company'] }}</span>
                        <input type="text" name="company_name" maxlength="120" autocomplete="organization" required>
                    </label>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['event_type'] }}</span>
                        <select name="event_type">
                            @foreach ($wizard['event_types'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <fieldset class="wizard-field-wide">
                        <legend>{{ $wizard['contact_method'] }}</legend>
                        <div class="wizard-pills">
                            @foreach (['whatsapp', 'phone', 'email'] as $method)
                                <label><input type="radio" name="contact_type" value="{{ $method }}" @checked($method === 'whatsapp')><span>{{ $wizard[$method] }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['contact_value'] }}</span>
                        <input type="text" name="contact_value" maxlength="120" autocomplete="tel" required>
                    </label>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['special'] }}</span>
                        <textarea name="special_request" rows="2" maxlength="500" placeholder="{{ $wizard['special_placeholder'] }}"></textarea>
                    </label>
                </div>

                <div data-step="5" class="wizard-step wizard-step-wide" hidden>
                    <dl class="wizard-summary" data-summary></dl>
                    <p class="wizard-discount" data-summary-discount hidden><span>{{ $wizard['discount'] }} <b data-summary-discount-percent></b></span> <strong data-summary-discount-amount></strong></p>
                    <p class="wizard-total"><span>{{ $wizard['estimated_total'] }}</span> <strong data-summary-total></strong></p>
                    <p class="wizard-hint">{{ $wizard['disclaimer'] }}</p>
                </div>

                <div class="wizard-error" data-wizard-error role="alert" hidden>
                    <p data-wizard-error-text></p>
                    <div class="wizard-alternatives" data-wizard-alternatives hidden>
                        <p>{{ $wizard['available_instead'] }}</p>
                        <ul></ul>
                    </div>
                </div>

                <div class="wizard-actions">
                    <button type="button" class="wizard-secondary" data-wizard-back hidden>{{ $wizard['back'] }}</button>
                    <button type="button" class="hub-action" data-wizard-next>{{ $wizard['next'] }} <span aria-hidden="true">→</span></button>
                    <button type="submit" class="hub-action" data-wizard-submit hidden>{{ $wizard['submit'] }}</button>
                </div>
            </form>
        </div>

        <div class="wizard-done" data-wizard-done hidden>
            <p class="hub-eyebrow">{{ $wizard['done_title'] }}</p>
            <p class="wizard-reference-label">{{ $wizard['reference'] }}</p>
            <p class="wizard-reference" data-done-reference></p>
            <p class="wizard-status">{{ $wizard['awaiting'] }}</p>
            <p class="mt-4 max-w-md text-sm leading-relaxed text-stone-600">{{ $wizard['done_hint'] }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a class="hub-action" data-done-whatsapp target="_blank" rel="noopener" hidden>{{ $wizard['send_whatsapp'] }} <span aria-hidden="true">↗</span></a>
                <a class="wizard-secondary" data-done-phone hidden>{{ $wizard['call_venue'] }}</a>
                <a class="wizard-secondary" data-done-email hidden>{{ $wizard['email_venue'] }}</a>
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit class="wizard-secondary">{{ $hub['back'] }}</a>
                <button type="button" class="wizard-secondary" data-wizard-reset>{{ $wizard['new_request'] }}</button>
            </div>
        </div>

        <script type="application/json" data-wizard-labels>@json($wizard)</script>
    </div>
</section>
