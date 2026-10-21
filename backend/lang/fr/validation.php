<?php

/*
 * Messages de validation en français, limités aux règles utilisées par l'API.
 * Une règle absente d'ici retombe sur le message anglais du framework.
 */

return [
    'alpha' => 'Le champ :attribute ne doit contenir que des lettres.',
    'date_format' => 'Le champ :attribute doit respecter le format :format.',
    'enum' => 'La valeur du champ :attribute est invalide.',
    'in' => 'La valeur du champ :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => [
        'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'min' => [
        'numeric' => 'Le champ :attribute doit être au moins égal à :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'regex' => 'Le format du champ :attribute est invalide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'size' => [
        'string' => 'Le champ :attribute doit contenir :size caractères.',
    ],
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',

    'attributes' => [
        'bonus_malus' => 'coefficient bonus-malus',
        'country' => 'pays',
        'driver_birth_date' => 'date de naissance du conducteur',
        'duration_months' => 'durée du contrat',
        'effective_date' => "date d'effet",
        'effective_from' => "date d'effet minimale",
        'effective_to' => "date d'effet maximale",
        'fiscal_power' => 'puissance fiscale',
        'license_date' => "date d'obtention du permis",
        'per_page' => 'nombre de résultats par page',
        'reference' => 'référence',
        'tariff_version' => 'version de tarif',
        'usage' => 'usage',
        'vehicle_category' => 'catégorie de véhicule',
        'zone' => 'zone',
    ],
];
