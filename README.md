# Consent manager

Cookie consent for Thelia 3 and the Flexy theme:

- **Collect.** A banner on the first visit, with three answers of the same weight: accept all, refuse all, customize. Customizing lists the categories the merchant set up, each with its description and the services it covers. A strictly necessary category cannot be refused, and says so.
- **Respect.** A third-party script only runs once its category is accepted. Modules hand their markup to the module's script gate, which holds it back until then.
- **Prove.** Each choice is recorded: date, accepted and refused categories, the version of what the visitor was shown, and a random visitor identifier that is not personal data. A proof is never modified.

The visitor changes their mind at any time from the "Cookie settings" link in the footer; refusing a category deletes its cookies. A cookie policy page (`/cookie-policy`) is generated from the categories and the declared services.

## Installation

```bash
composer require thelia/consent-manager-module
php Thelia module:activate ConsentManager
```

The module ships inactive: the banner changes how the shop looks and its text is a legal notice the merchant must review before going live.

It needs Thelia 3.2 and the Flexy theme (the banner uses Flexy's `Button`, `Modal` and `Checkbox` components and its `layout.body.bottom` and `layout.footer.links` hook points).

## Back-office

Modules → Consent manager:

| Screen | What it does |
|---|---|
| Banner and durations | Banner title and text per language (empty: the module wording), how long a choice is kept before asking again (180 days by default, 395 at most), how long a proof is kept (0: forever) |
| Categories | Add, describe, reorder and delete categories. Three to start with: `necessary` (strictly necessary), `analytics`, `marketing` |
| Services | Services declared by modules and services added by hand; move any of them to another category |
| Proofs of consent | Find the proofs of a visitor from their identifier, and the exact content of the policy version they saw |

Any change to the banner, the categories or the services publishes a new policy version, and visitors are asked again.

## Retention

Proofs older than the configured period are deleted by the core purge:

```bash
php Thelia maintenance:purge --dry-run   # reports what would go
php Thelia maintenance:purge
```

## For a module that loads a third-party script

Two steps, and no dependency on this module: both use string identifiers, so the module keeps working when the consent manager is not installed.

1. Declare the service, by listening to `consent_manager.services.collect`:

```php
use ConsentManager\Event\CollectConsentServicesEvent;
use ConsentManager\Event\ConsentServiceDeclaration;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class MyConsentDeclaration
{
    #[AsEventListener(event: 'consent_manager.services.collect')]
    public function declare(CollectConsentServicesEvent $event): void
    {
        $event->add(new ConsentServiceDeclaration(
            code: 'my_widget',
            title: 'My widget',
            categoryCode: 'marketing',          // a suggestion: the merchant can move it
            provider: 'Widget Inc.',
            cookies: ['_mw', '_mw_*'],          // deleted when the visitor refuses; * matches a prefix
            privacyUrl: 'https://widget.example/privacy',
            description: 'Shows the widget.',
            translationDomain: 'mymodule',      // title and description are translated in it
        ));
    }
}
```

2. Gate the markup, through the `consent_manager.script_gate` service, wired as an optional argument:

```php
// MyModule::configureServices()
$services->get(MyThemeHook::class)
    ->arg('$consentGate', service('consent_manager.script_gate')->nullOnInvalid());

// MyThemeHook
$html = $this->twig->render('@MyModuleModule/script.html.twig');

return null !== $this->consentGate ? $this->consentGate->gate($html, 'my_widget') : $html;
```

The gate turns `<script>` into `type="text/plain"`, removes the `src` of `<iframe>` and `<img>`, and drops `<noscript>` blocks. The page stays the same for every visitor, so it can be served from a shared cache; the browser runs the markup once the category is accepted. `GoogleTagManager` is the first module built this way.

In the browser, `window.theliaConsent.has('analytics')` tells whether a category is accepted, and a `thelia:consent` event is dispatched on `document` with `detail.accepted` each time the choice is applied. Server side, `consent_manager.consent_state` reads the consent cookie, for responses that are never shared between visitors.

## Headless front

| Endpoint | |
|---|---|
| `GET /api/front/consent-configuration?locale=fr_FR` | Current policy version, categories and services |
| `POST /api/front/consents` | Records a choice: `{"visitorId": null, "policy": 3, "accepted": ["analytics"], "refused": ["marketing"]}`. A category not listed is refused, refusing a strictly necessary one is a 422. Answers the proof and the value of the `thelia_consent` cookie |
| `GET /api/front/consents/{visitorId}` | The latest choice of a visitor; `current` is false once the policy changed |

Recording is limited to 30 choices an hour per address.

## Tests

From the root of a Thelia checkout the module is installed in, against the test database:

```bash
composer phpc -- vendor/bin/phpunit -c local/modules/ConsentManager/phpunit.xml.dist
```

The tests activate the module in the test database if it is not active yet.
