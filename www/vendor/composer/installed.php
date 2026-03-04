<?php return array(
    'root' => array(
        'name' => 'limahost/eletron',
        'pretty_version' => '1.0.0+no-version-set',
        'version' => '1.0.0.0',
        'reference' => null,
        'type' => 'project',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'limahost/eletron' => array(
            'pretty_version' => '1.0.0+no-version-set',
            'version' => '1.0.0.0',
            'reference' => null,
            'type' => 'project',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'mike42/escpos-php' => array(
            'pretty_version' => 'dev-development',
            'version' => 'dev-development',
            'reference' => 'f414320fc510afcacd4cbb75902f827399dee429',
            'type' => 'library',
            'install_path' => __DIR__ . '/../mike42/escpos-php',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
        'mike42/gfx-php' => array(
            'pretty_version' => 'v0.6',
            'version' => '0.6.0.0',
            'reference' => 'ed9ded2a9298e4084a9c557ab74a89b71e43dbdb',
            'type' => 'library',
            'install_path' => __DIR__ . '/../mike42/gfx-php',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
