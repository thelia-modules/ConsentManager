<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ConsentManager\Service;

use ConsentManager\Model\ConsentCategory;
use ConsentManager\Model\ConsentCategoryQuery;

/**
 * The three categories a shop starts with. The merchant renames, describes, adds or
 * removes them afterwards; only the strictly necessary one cannot be refused.
 */
final class DefaultCategories
{
    public const NECESSARY = 'necessary';
    public const ANALYTICS = 'analytics';
    public const MARKETING = 'marketing';

    private const DEFINITIONS = [
        self::NECESSARY => [
            'required' => 1,
            'en_US' => ['Strictly necessary', 'Needed for the shop to work: your cart, your session and the record of this choice. They cannot be refused.'],
            'fr_FR' => ['Strictement nécessaires', 'Indispensables au fonctionnement de la boutique : votre panier, votre session et la mémoire de ce choix. Ils ne peuvent pas être refusés.'],
        ],
        self::ANALYTICS => [
            'required' => 0,
            'en_US' => ['Audience measurement', 'Let us count visits and see how the shop is used, to improve it.'],
            'fr_FR' => ['Mesure d\'audience', 'Nous permettent de compter les visites et de voir comment la boutique est utilisée, pour l\'améliorer.'],
        ],
        self::MARKETING => [
            'required' => 0,
            'en_US' => ['Advertising', 'Let our partners show you advertising that matches your interests, here and on other sites.'],
            'fr_FR' => ['Publicité', 'Permettent à nos partenaires de vous montrer des publicités adaptées à vos centres d\'intérêt, ici et sur d\'autres sites.'],
        ],
    ];

    public static function create(): void
    {
        $position = 0;
        foreach (self::DEFINITIONS as $code => $definition) {
            ++$position;
            if (null !== ConsentCategoryQuery::create()->findOneByCode($code)) {
                continue;
            }

            $category = (new ConsentCategory())
                ->setCode($code)
                ->setRequired($definition['required'])
                ->setPosition($position);

            foreach (['en_US', 'fr_FR'] as $locale) {
                [$title, $description] = $definition[$locale];
                $category->setLocale($locale)->setTitle($title)->setDescription($description);
            }

            $category->save();
        }
    }
}
