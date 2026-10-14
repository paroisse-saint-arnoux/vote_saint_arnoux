<?php
// Grilles d'évaluation, par étape de la consultation. Les pondérations de chaque étape totalisent 100.
// 'mod' => true : critère noté par le seul MOD, sa note étant reprise pour tous les membres du jury.

return [
// ---------------------------------------------------------------- Étape 1 : sélection des candidatures
1 => [
    1 => [
        'short'  => 'Vision',
        'title'  => 'Vision du projet — compréhension des enjeux pastoraux et du programme',
        'weight' => 25,
        'low'    => 'Note interchangeable avec n’importe quelle consultation ; vocabulaire de programme recopié sans appropriation.',
        'high'   => 'Compréhension de la dimension pastorale autant que technique : le candidat a saisi qu’il s’agit d’un lieu de vie communautaire, pas seulement d’un ERP.',
    ],
    2 => [
        'short'  => 'Motivation',
        'title'  => 'Lettre de candidature et motivation',
        'weight' => 20,
        'low'    => 'Lettre type ; erreurs sur le nom du maître d’ouvrage ou de l’opération ; silence sur la disponibilité.',
        'high'   => 'Motivation située : pourquoi cette opération, pour cette agence, maintenant. Lettre signée du mandataire, engageant le groupement sur sa disponibilité aux dates du concours.',
    ],
    3 => [
        'short'  => 'Groupement',
        'title'  => 'Organisation du groupement — équipe, moyens, complémentarité architecte et bureaux d’études',
        'weight' => 17,
        'low'    => 'Compétences déclarées sans titulaire identifié ; absence de la compétence cultuelle, acoustique, hydraulique, de scénographie ou de démolition ; groupement constitué pour l’occasion sans antécédent.',
        'high'   => 'Toutes les compétences du règlement §4 couvertes et nommées, avec le bureau d’études qui les porte — y compris la scénographie et la compétence construction et démolition. Complémentarité démontrée par des collaborations antérieures.',
    ],
    4 => [
        'short'  => 'Références',
        'title'  => 'Références — expérience cultuelle ou diocésaine, établissement recevant du public ; caractère significatif et adéquation au projet',
        'weight' => 15,
        'low'    => 'Liste de projets prestigieux sans lien avec l’opération ; rôle du candidat non explicité ; références portées par un cotraitant et non par le mandataire ; contacts non joignables.',
        'high'   => 'Deux références au moins réellement comparables : ERP recevant du public, lieu cultuel, équipement institutionnel, site contraint ou projet phasé. Le rôle exact du candidat y est précisé, ainsi que le coût et l’issue. Chaque référence est vérifiable : maître d’ouvrage, contact joignable, année de livraison.',
    ],
    5 => [
        'short'  => 'Assurances',
        'title'  => 'Assurances, garanties professionnelles et solidité financière du mandataire',
        'weight' => 13,
        'low'    => 'Attestations périmées, manquantes pour un cotraitant, ou montants de garantie sans rapport avec le coût de l’ouvrage.',
        'high'   => 'Attestations en cours de validité pour le mandataire et chaque cotraitant, décennale mentionnant expressément l’opération ou son objet.',
    ],
    6 => [
        'short'  => 'RSE',
        'title'  => 'Responsabilité sociétale et environnementale du candidat, dont compétence et références en performance environnementale',
        'weight' => 10,
        'note'   => 'Le critère RSE porte sur la démarche du candidat lui-même, pas sur le projet ; la qualité environnementale annoncée du projet relève des sélections 2 et 3',
        'low'    => 'Déclaration d’intention sans preuve ; confusion avec la performance environnementale du projet, qui s’apprécie en Sélections 2 et 3.',
        'high'   => 'Démarche propre à l’agence et à ses cotraitants, documentée, avec au moins une référence de performance environnementale.',
    ],
],

// ---------------------------------------------------------------- Étape 2 : livrable des 10 candidats retenus
2 => [
    1 => [
        'short'  => 'Réglementaire',
        'title'  => 'Contraintes réglementaires',
        'weight' => 25,
        'mod'    => true,
        'points' => [
            'Zone Uf du PLU : lieu de culte admis (Uf.2) ; logement non admis en neuf, donc traitement du presbytère au permis unique ; hauteur, emprise et limites non réglementées pour un équipement d’intérêt collectif ; stationnement sans norme chiffrée ; révision du PLU en cours ; le PPRIF prévaut s’il est plus restrictif.',
            'PPRIF secteur B1a : accès des secours, hydrant à moins de 150 m, dispositions constructives de l’annexe 1, débroussaillement légal de 100 m dépassant le tènement, point ouvert de l’aspersion.',
            'OAP Déplacements 3.2 : recul d’environ 10 m reporté sur plan, incertitude sur la base de mesure signalée ; accès par le chemin de Barnarac et l’allée du Pré des Roures, aucune façade sur le chemin du Bon Jas (BE 161 départementale).',
            'Sismicité : zone 3 modérée, jamais zone 4 ; catégorie d’importance renvoyée à l’AVP ; conséquences constructives.',
            'Cadre ERP et procédures : église type V 300 personnes, centre 100 personnes ; aucun espace boisé classé ; dépôt du permis le 14 mai 2027.',
        ],
    ],
    2 => [
        'short'  => 'Corridor / GEMAPI',
        'title'  => 'Corridor écologique et gestion GEMAPI',
        'weight' => 20,
        'mod'    => true,
        'points' => [
            'Corridor et continuités : le tènement n’est pas assujetti au corridor au sens strict mais à l’élément de continuité écologique ; pleine terre à 25 % de l’unité foncière, soit 2 184 m² au minimum, parkings et voiries exclus ; oliviers localisés, abattage interdit sans dérogation.',
            'GEMAPI et eaux pluviales : règlement CASA de 2021 — 100 litres par m² nouvellement imperméabilisé, infiltration privilégiée, vidange en douze heures, surverse à la parcelle ; topographie et sens d’écoulement lus sur le levé ; le vallon de Barnarac ne longe pas le site ; dispositifs proposés (infiltration, réemploi des rétentions existantes, buttes paysagères, raccordement).',
            'Sols : études G1 exploitées — calcaires et dolomies du Lias, altérés et fracturés — pour fondations et infiltration.',
        ],
    ],
    3 => [
        'short'  => 'Bioclimatique',
        'title'  => 'Approche bioclimatique et insertion paysagère',
        'weight' => 20,
        'points' => [
            'Bioclimatique : orientation plein sud et ensoleillement, vents, confort d’été, référence RE2020 sans label et raisonnement en coût global ; matériaux précisés — pierres du site réemployées, façades sans tout bois (préférence du maître d’ouvrage).',
            'Paysage : lecture du grand paysage boisé et en pente, premier crayon du plan de plantation cohérent avec la pleine terre, extérieurs vécus (parvis, enfants, convivialité), murs et restanques existants.',
        ],
    ],
    4 => [
        'short'  => 'Insertion',
        'title'  => 'Insertion dans le site et avoisinances',
        'weight' => 20,
        'points' => [
            'Insertion technique : niveaux et nivellements représentés (volumes et vues), plan d’implantation cohérent avec reculs, pleine terre, oliviers et accès ; démolition du bâti existant (amiante, diagnostics, permis de démolir en mission de base) ; réseaux.',
            'Avoisinances : collège, équipements, route de Nice, habitations ; nuisances d’un ERP de 300 personnes ; vues et vis-à-vis ; limites exactes (BE 161 et BE 158 hors tènement) ; chantier.',
        ],
    ],
    5 => [
        'short'  => 'Méthode',
        'title'  => 'Qualité méthodologique et clarté, sur l’ensemble',
        'weight' => 15,
        'points' => [
            'Forme : cinq pages A4, carnet A3, document de quatre pages, structure selon les cinq critères.',
            'Méthode : sources citées ; contraintes certaines, à vérifier et marges d’interprétation distinguées ; questions aux services listées ; impacts des prescriptions identifiés ; carte de synthèse des contraintes.',
            'Clarté et exactitude : lisibilité, cohérence texte-plans, aucune erreur de fait sur le site ou le programme, aucune esquisse ni élément de prix d’honoraires.',
        ],
    ],
    6 => [
        'short'  => 'Note d’accomp.',
        'title'  => 'Note d’accompagnement',
        'weight' => 0,
        'points' => [
            'Premières intentions.',
            'Références ERP.',
            'Estimation du coût.',
            'Représentant sur place.',
        ],
    ],
],
];
