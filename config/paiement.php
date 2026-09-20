<?php
// config/paiement.php
// Configuration centralisée des modes de paiement (Wave & Orange Money)

return [
    'frais_adhesion' => 2000, // FCFA (0 FCFA si le membre possède déjà sa carte physique)
    
    // Wave Business (Mode de paiement mobile exclusif)
    'wave' => [
        'nom'           => 'Caisse Dahira (Wave Business)',
        'telephone'     => '78 823 24 79',
        'raw_telephone' => '788232479',
        // Lien direct Wave Business qui ouvre l'application Wave du client sur mobile :
        'lien_paiement' => 'https://pay.wave.com/m/M_dahira_akr',
        'logo'          => '/assets/wave-logo.png',
        'icon'          => '/assets/wave-icon.png'
    ]
];

