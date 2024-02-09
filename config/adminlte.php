<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Title
    |--------------------------------------------------------------------------
    |
    | Here you can change the default title of your admin panel.
    |
    | For detailed instructions you can look the title section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/6.-Basic-Configuration
    |
    */

    'title' => '',
    'title_prefix' => '',
    'title_postfix' => '| Sefar Universal',

    /*
    |--------------------------------------------------------------------------
    | Favicon
    |--------------------------------------------------------------------------
    |
    | Here you can activate the favicon.
    |
    | For detailed instructions you can look the favicon section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/6.-Basic-Configuration
    |
    */

    'use_ico_only' => false,
    'use_full_favicon' => false,

    /*
    |--------------------------------------------------------------------------
    | Logo
    |--------------------------------------------------------------------------
    |
    | Here you can change the logo of your admin panel.
    |
    | For detailed instructions you can look the logo section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/6.-Basic-Configuration
    |
    */

    'logo' => '<b>sefar</b>',
    'logo_img' => 'vendor/adminlte/dist/img/LogoSefar.png',
    'logo_img_class' => 'brand-image img-circle elevation-3',
    'logo_img_xl' => null,
    'logo_img_xl_class' => 'brand-image-xs',
    'logo_img_alt' => 'Sefar Universal',

    /*
    |--------------------------------------------------------------------------
    | User Menu
    |--------------------------------------------------------------------------
    |
    | Here you can activate and change the user menu.
    |
    | For detailed instructions you can look the user menu section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/6.-Basic-Configuration
    |
    */

    'usermenu_enabled' => true,
    'usermenu_header' => true,
    /* 'usermenu_header_class' => 'bg-info', */
    'usermenu_header_class' => 'cfrSefar ctaSefar',
    'usermenu_image' => false,
    'usermenu_desc' => true,
    'usermenu_profile_url' => true,

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | Here we change the layout of your admin panel.
    |
    | For detailed instructions you can look the layout section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/7.-Layout-and-Styling-Configuration
    |
    */

    'layout_topnav' => null,
    'layout_boxed' => null,
    'layout_fixed_sidebar' => true,
    'layout_fixed_navbar' => true,
    'layout_fixed_footer' => null,

    /*
    |--------------------------------------------------------------------------
    | Authentication Views Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the authentication views.
    |
    | For detailed instructions you can look the auth classes section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/7.-Layout-and-Styling-Configuration
    |
    */

    'classes_auth_card' => 'card-outline card-primary',
    'classes_auth_header' => '',
    'classes_auth_body' => '',
    'classes_auth_footer' => '',
    'classes_auth_icon' => '',
    'classes_auth_btn' => 'btn-flat btn-primary',

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the admin panel.
    |
    | For detailed instructions you can look the admin panel classes here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/7.-Layout-and-Styling-Configuration
    |
    */

    'classes_body' => '',
    'classes_brand' => 'cfrSefar',
    'classes_brand_text' => 'ct_blanco',
    'classes_content_wrapper' => '',
    'classes_content_header' => '',
    'classes_content' => '',
    'classes_sidebar' => 'sidebar-dark-secondary elevation-4',  // white
    'classes_sidebar_nav' => '',
    'classes_topnav' => 'navbar-white navbar-light',
    'classes_topnav_nav' => 'navbar-expand',
    'classes_topnav_container' => 'container',

    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar of the admin panel.
    |
    | For detailed instructions you can look the sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/7.-Layout-and-Styling-Configuration
    |
    */

    'sidebar_mini' => true,
    'sidebar_collapse' => false,
    'sidebar_collapse_auto_size' => false,
    'sidebar_collapse_remember' => false,
    'sidebar_collapse_remember_no_transition' => true,
    'sidebar_scrollbar_theme' => 'os-theme-light',
    'sidebar_scrollbar_auto_hide' => 'l',
    'sidebar_nav_accordion' => true,
    'sidebar_nav_animation_speed' => 300,

    /*
    |--------------------------------------------------------------------------
    | Control Sidebar (Right Sidebar)
    |--------------------------------------------------------------------------
    |
    | Here we can modify the right sidebar aka control sidebar of the admin panel.
    |
    | For detailed instructions you can look the right sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/7.-Layout-and-Styling-Configuration
    |
    */

    'right_sidebar' => false,
    'right_sidebar_icon' => 'fas fa-bars',
    'right_sidebar_theme' => 'dark',
    'right_sidebar_slide' => true,
    'right_sidebar_push' => true,
    'right_sidebar_scrollbar_theme' => 'os-theme-light',
    'right_sidebar_scrollbar_auto_hide' => 'l',

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    |
    | Here we can modify the url settings of the admin panel.
    |
    | For detailed instructions you can look the urls section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/6.-Basic-Configuration
    |
    */

    'use_route_url' => false,
    'dashboard_url' => '/',
    'logout_url' => 'logout',
    'login_url' => 'login',
    'register_url' => 'register',
    'password_reset_url' => 'reset-password',
    'password_email_url' => '' /* 'password/email' */,
    'profile_url' => false,

    /*
    |--------------------------------------------------------------------------
    | Laravel Mix
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Laravel Mix option for the admin panel.
    |
    | For detailed instructions you can look the laravel mix section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/9.-Other-Configuration
    |
    */

    'enabled_laravel_mix' => false,
    'laravel_mix_css_path' => 'css/app.css',
    'laravel_mix_js_path' => 'js/app.js',

    /*
    |--------------------------------------------------------------------------
    | Menu Items
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar/top navigation of the admin panel.
    |
    | For detailed instructions you can look here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/8.-Menu-Configuration
    |
    */

    'menu' => [
        [
            'text' => 'search',
            'search' => false,
            'topnav' => true,
        ],
        /* [
            'text' => 'Al lado de search',
            'url' => '#',
            'topnav' => true,
        ], */
        /* [
            'text' => 'Al lado derecho',
            'url' => '#',
            'topnav_right' => true,
        ], */
        /* [
            'text' => 'Dentro de usuario',
            'url' => '#',
            'topnav_user' => true,
        ], */
        [
            'text' => 'blog',
            'url'  => 'admin/blog',
            'can'  => 'no_definido',
        ],
        [
            'text'        => 'Panel Administrativo',
            'route'       => 'inicio',
            'icon'        => 'fa-fw fas fa-tachometer-alt',
            'icon_color'  => 'white',
            /* 'label'       => 1,
            'label_color' => 'success', */
            'can'  => 'administrador',
        ],
        /* *** ACCESOS *** */
        [
            'text'        => 'Accesos',
            'icon'        => 'fa-fw fas fa-key',
            'icon_color'  => 'white',
            'can'  => 'administrador',
            'submenu' => [
                [
                    'text'          => 'Usuarios',
                    'icon'          => 'fa-fw fas fa-users',
                    'icon_color'    => 'white',
                    'url'           => 'users',
                    'can'           => 'crud.users.index',
                ],
                [
                    'text'          => 'Roles',
                    'icon'          => 'fa-fw fas fa-user-tag',
                    'icon_color'    => 'white',
                    'url'           => 'roles',
                    'can'           => 'crud.roles.index',
                ],
                [
                    'text'          => 'Permisos',
                    'icon'          => 'fa-fw fas fa-universal-access',
                    'icon_color'    => 'white',
                    'url'           => 'permissions',
                    'can'           => 'crud.permissions.index',
                ]
            ],
        ],
        /* *** Pasaportes Incorrectos *** */
        [
            'text'          => 'Pasaportes Incorrectos',
            'icon'          => 'fa-fw fas fa-users',
            'icon_color'    => 'white',
            'url'           => 'fixpassport',
            'can'           => 'administrador',
        ],

        /* *** Administracion de PAGOS *** */
        [
            'text'        => 'Admin. Pagos',
            'icon'        => 'fa-fw fa fa-credit-card',
            'icon_color'  => 'white',
            'can'  => 'admin.payments',
            'submenu' => [
                /* *** COMPROBANTES DE PAGO *** */
                [
                    'text'          => 'Comprobantes',
                    'icon'          => 'fa-fw fas fa-file-invoice',
                    'icon_color'    => 'white',
                    'url'           => 'comprobantes',
                    'can'           => 'crud.comprobantes.index',
                ],

                /* *** UTILIDADES USANDO LA API DE STRIPE *** */
                [
                    'text'          => 'Verif. Pagos de Cliente',
                    'icon'          => 'fa-fw fab fa-cc-stripe',
                    'icon_color'    => 'white',
                    'url'           => 'stripeverify',
                    'can'           => 'crud.stripeverify.index',
                ],

                [
                    'text'          => 'Hist. Pagos Stripe',
                    'icon'          => 'fa-fw fab fa-cc-stripe',
                    'icon_color'    => 'white',
                    'url'           => 'listLatestStripeData',
                    'can'           => 'crud.stripeverify.index',
                ]
            ],
        ],

        /* *** MONDAY Y SUS RESPECTIVAS MÉTRICAS *** */
        [
            'text'        => 'Monday',
            'icon'        => 'fa-fw fas fa-calendar-check',
            'icon_color'  => 'white',
            'can'  => 'administrador',
            'submenu' => [
                /*[
                    'text'          => 'Reportes',
                    'icon'          => 'fa-fw fas fa-calendar-check',
                    'icon_color'    => 'white',
                    'url'           => 'mondayreportes',
                    'can'           => 'crud.mondayreportes.index',
                ],*/
                [
                    'text'          => 'Registrar en Monday',
                    'icon'          => 'fa-fw fas fa-calendar-check',
                    'icon_color'    => 'white',
                    'url'           => 'mondayregistrar',
                    'can'           => 'crud.mondayreportes.index',
                ]
            ],
        ],

        /* *** CUPONES *** */
        [
            'text'          => 'Cupones',
            'icon'          => 'fa-fw fa fa-gift',
            'icon_color'    => 'white',
            'url'           => 'coupons',
            'can'           => 'crud.coupons.index',
        ],

        /* *** Servicios *** */
        [
            'text'          => 'Servicios',
            'icon'          => 'fa-fw fa fa-server',
            'icon_color'    => 'white',
            'can'           => 'crud.servicios.index',
            'url'           => 'servicios',
        ],

        /* *** Reportes *** */
        [
            'text'        => 'Reportes',
            'icon'        => 'fa-fw fa fa-file',
            'icon_color'  => 'white',
            'can'  => 'administrador',
            'submenu' => [
                [
                    'text'          => 'Reportes Generados',
                    'icon'          => 'fa-fw fa fa-file',
                    'icon_color'    => 'white',
                    'url'           => 'reports',
                    'can'           => 'crud.reports.index',
                ],
                [
                    'text'          => 'Referidos de Hubspot',
                    'icon'          => 'fa-fw fa fa-file',
                    'icon_color'    => 'white',
                    'url'           => 'hsreferidos',
                    'can'           => 'crud.hsreferidos.index',
                ],
            ],
        ],

        /* *** MENÚ PARA GENEALOGISTAS E INVESTIGADORES *** */
        [
            'text'        => 'Genealogistas',
            'icon'        => 'fa-fw fab fa-pagelines',
            'icon_color'  => 'white',
            'can'  => ['genealogista', 'produccion'],
            'submenu' => [
                [
                    'text'          => 'Clientes',
                    'icon'          => 'fa-fw fas fa-id-card',
                    'icon_color'    => 'white',
                    'route'         => '',
                    'can'           => 'crud.clients.index',
                ],
                [
                    'text'          => 'Clientes y ancestros',
                    'icon'          => 'fa-fw fas fa-user-plus',
                    'icon_color'    => 'white',
                    'route'         => 'crud.agclientes.index',
                    'can'           => 'crud.agclientes.index',
                ],
                [
                    'text'          => 'Clientes y familiares',
                    'icon'          => 'fa-fw fas fa-user-shield',
                    'icon_color'    => 'white',
                    'route'         => 'crud.families.index',
                    'can'           => 'crud.families.index',
                ],
                [
                    'text'          => 'Últimas modificaciones',
                    'icon'          => 'fa-fw fas fa-portrait',
                    'icon_color'    => 'white',
                    'url'           => 'no_definido',
                    'can'           => 'no_definido',
                ],
                [
                    'text'          => 'Documentos clientes',
                    'icon'          => 'fa-fw fas fa-passport',
                    'icon_color'    => 'white',
                    'route'         => 'crud.files.index',
                    'can'           => 'crud.files.index',
                ],
            ],
        ],

        [
            'text'        => 'Herramientas GED',
            'icon'        => 'fa-fw fab fa-pagelines',
            'icon_color'  => 'white',
            'can'  => ['administrador'],
            'submenu' => [
                [
                    'text'          => 'Exportar Gedcom',
                    'icon'          => 'fa-fw fab fa-pagelines',
                    'icon_color'    => 'white',
                    'route'         => 'gedcomexport',
                    'can'           => ['administrador'],
                ],
            ],
        ],

        /* *** REGISTROS ONIDEX, DIEX Y MAISANTA *** */
        [
            'text'        => 'Consultas',
            'icon'        => 'fa-fw fas fa-search',
            'icon_color'  => 'white',
            'can'  => 'consultas.onidex.index',
            'submenu' => [
                [
                    'text'          => 'Registros Onidex',
                    'icon'          => 'fa-fw fas fa-id-card',
                    'icon_color'    => 'white',
                    /* 'url'           => 'consultaodx', */
                    'route'         => 'consultas.onidex.index',
                    'can'           => 'consultas.onidex.index',
                ],
                [
                    'text'          => 'Diex',
                    'icon'          => 'fa-fw fas fa-portrait',
                    'icon_color'    => 'white',
                    'url'           => 'roles',
                    'can'           => 'no_definido',
                ],
                [
                    'text'          => 'Maisanta',
                    'icon'          => 'fa-fw fas fa-portrait',
                    'icon_color'    => 'white',
                    'url'           => 'permissions',
                    'can'           => 'no_definido',
                ],
            ],
        ],

        /* *** MENÚ PARA DOCUMENTALISTAS *** */
        [
            'text'        => 'Control de documentos',
            'icon'        => 'fa-fw fas fa-file-import',
            'icon_color'  => 'white',
            'can'  => 'documentalista',
            'submenu' => [
                /* [
                    'text'          => 'Biblioteca',
                    'icon'          => 'fas fa-book-reader',
                    'icon_color'    => 'white',
                    'route'         => 'crud.libraries.index',
                    'can'           => 'crud.libraries.index',
                ], */
                [
                    'text'          => 'Libros',
                    'icon'          => 'fa-fw fas fa-book-reader',
                    'icon_color'    => 'white',
                    'route'         => 'crud.books.index',
                    'can'           => 'crud.books.index',
                ],
                [
                    'text'          => 'Miscelaneos',
                    'icon'          => 'fa-fw fas fa-file',
                    'icon_color'    => 'white',
                    'route'         => 'crud.miscelaneos.index',
                    'can'           => 'crud.miscelaneos.index',
                ],
            ],
        ],

        /* *** TABLAS GENERALES *** */
        [
            'text'        => 'Tablas Generales',
            'icon'        => 'fa-fw fas fa-table',
            'icon_color'  => 'white',
            'can'  => 'administrador',
            'submenu' => [
                [
                    'text'          => 'Paises',
                    'icon'          => 'fa-fw fas fa-flag',
                    'icon_color'    => 'white',
                    'route'         => 'crud.countries.index',
                    'can'           => 'administrador',
                ],
                [
                    'text'          => 'Parentescos',
                    'icon'          => 'fa-fw fab fa-first-order',
                    'icon_color'    => 'white',
                    'route'         => 'crud.parentescos.index',
                    'can'           => 'administrador',
                ],
                [
                    'text'          => 'Lado - Parentesco',
                    'icon'          => 'fa-fw fas fa-fingerprint',
                    'icon_color'    => 'white',
                    'route'         => 'crud.lados.index',
                    'can'           => 'administrador',
                ],
                [
                    'text'          => 'Conexión - Parentesco',
                    'icon'          => 'fa-fw fas fa-sitemap',
                    'icon_color'    => 'white',
                    'route'         => 'crud.connections.index',
                    'can'           => 'administrador',
                ],
                [
                    'text'          => 'Tipos de documentos',
                    'icon'          => 'fa-fw fas fa-file-alt',
                    'icon_color'    => 'white',
                    'route'         => 'crud.t_files.index',
                    'can'           => 'administrador',
                ],
                [
                    'text'          => 'Tipos de formatos',
                    'icon'          => 'fa-fw fas fa-print',
                    'icon_color'    => 'white',
                    'route'         => 'crud.formats.index',
                    'can'           => 'administrador',
                ],
            ],
        ],

        /* *** CLIENTES *** */
        [
            'text'        => 'Menú de opciones',
            'icon'        => 'fa-fw fas fa-caret-square-down',
            'icon_color'  => 'blue',
            'can'  => 'cliente',
            'submenu' => [
                [
                    'text'          => 'Pago del análisis',
                    'classes'       => "btn_pay",
                    'icon'          => 'fa-fw fas fa-money-bill',
                    'icon_color'    => 'yellow',
                    'route'         => 'clientes.pay',
                    'can'           => 'pay.services',
                ],
                [
                    'text'          => 'Completar información',
                    'classes'       => "btn_getinfo",
                    'icon'          => 'fa-fw fas fa-user-plus',
                    'icon_color'    => 'yellow',
                    'route'         => 'clientes.getinfo',
                    'can'           => 'finish.register',
                ],
                [
                    'text'          => 'Cargar árbol',
                    'classes'       => "btn_tree",
                    'icon'          => 'fa-fw fas fa-sitemap',
                    'icon_color'    => 'yellow',
                    'route'         => 'clientes.tree',
                    'can'           => 'cliente',
                ],/*
                [
                    'text'          => 'Estatus de mi Proceso',
                    'icon'          => 'fa-fw fas fa-exclamation',
                    'icon_color'    => 'yellow',
                    'url'           => 'my_status',
                    'can'           => 'cliente',
                ],*/
                [
                    'text'          => 'Perfil de usuario',
                    'icon'          => 'fa-fw fas fa-user-cog',
                    'icon_color'    => 'yellow',
                    'url'           => 'user/profile',
                    'can'           => 'cliente',
                ],
            ],
        ],
        [
            'text'          => 'Finalizar carga',
            'icon'          => 'fa-fw fas fa-sign-out-alt',
            'icon_color'    => 'blue',
            'route'         => 'clientes.salir',
            'can'           => 'cliente',
        ],

        /* *** PRUEBAS *** */
        [
            'text'        => 'Pruebas',
            'icon'        => 'fa-fw fas fa-grimace',
            'icon_color'  => 'yellow',
            'can'  => 'administrador',
            'submenu' => [
                [
                    'text'          => 'Flex Tailwind',
                    'icon'          => 'fa-fw fab fa-buromobelexperte',
                    'icon_color'    => 'yellow',
                    'url'           => 'flex',
                    'can'           => 'administrador',
                ],
                [
                    'text'          => 'Pruebas de Correos',
                    'icon'          => 'fa-fw fas fa-envelope',
                    'icon_color'    => 'yellow',
                    'url'           => 'testcorreos',
                    'can'           => 'administrador',
                ],
                [
                    'text'          => 'MVC Agclientes',
                    'icon'          => 'fa-fw fab fa-intercom',
                    'icon_color'    => 'blue',
                    'url'           => 'agclientesp',
                    'can'           => 'administrador',
                ],
                [
                    'text'          => 'Ventana Modal',
                    'icon'          => 'fa-fw fas fa-window-restore',
                    'icon_color'    => 'red',
                    'url'           => 'vmodal',
                    'can'           => 'administrador',
                ],
                [
                    'text'          => 'Enlace para registro',
                    'icon'          => 'fa-fw fas fa-user-circle',
                    'icon_color'    => 'green',
                    'url'           => 'registro',
                    'can'           => 'administrador',
                ],
            ],
        ],


        [
            'header' => 'account_settings',
            'can'  => 'no_definido',
        ],
        [
            'text' => 'profile',
            'url'  => 'user/profile',
            'icon' => 'fa-fw fas fa-fw fa-user',
            'can'  => 'no_definido',
        ],
        [
            'text' => 'change_password',
            'url'  => 'reset-password',
            'icon' => 'fa-fw fas fa-fw fa-lock',
            'can'  => 'no_definido',
        ],
        [
            'text'    => 'multilevel',
            'icon'    => 'fa-fw fas fa-fw fa-share',
            'can'  => 'no_definido',
            'submenu' => [
                [
                    'text' => 'level_one',
                    'url'  => '#',
                    'can'  => 'no_definido',
                ],
                [
                    'text'    => 'level_one',
                    'url'     => '#',
                    'can'  => 'no_definido',
                    'submenu' => [
                        [
                            'text' => 'level_two',
                            'url'  => '#',
                            'can'  => 'no_definido',
                        ],
                        [
                            'text'    => 'level_two',
                            'url'     => '#',
                            'can'  => 'no_definido',
                            'submenu' => [
                                [
                                    'text' => 'level_three',
                                    'url'  => '#',
                                    'can'  => 'no_definido',
                                ],
                                [
                                    'text' => 'level_three',
                                    'url'  => '#',
                                    'can'  => 'no_definido',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'text' => 'level_one',
                    'url'  => '#',
                    'can'  => 'no_definido',
                ],
            ],
        ],
        [
            'header' => 'labels',
            'can'  => 'no_definido',
        ],
        [
            'text'       => 'important',
            'icon_color' => 'red',
            'url'        => '#',
            'can'  => 'no_definido',
        ],
        [
            'text'       => 'warning',
            'icon_color' => 'yellow',
            'url'        => '#',
            'can'  => 'no_definido',
        ],
        [
            'text'       => 'information',
            'icon_color' => 'cyan',
            'url'        => '#',
            'can'  => 'no_definido',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Filters
    |--------------------------------------------------------------------------
    |
    | Here we can modify the menu filters of the admin panel.
    |
    | For detailed instructions you can look the menu filters section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/8.-Menu-Configuration
    |
    */

    'filters' => [
        JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\HrefFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\SearchFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ActiveFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ClassesFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\LangFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\DataFilter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins Initialization
    |--------------------------------------------------------------------------
    |
    | Here we can modify the plugins used inside the admin panel.
    |
    | For detailed instructions you can look the plugins section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/9.-Other-Configuration
    |
    */

    'plugins' => [
        'Datatables' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js',
                ],
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css',
                ],
            ],
        ],
        'Select2' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.css',
                ],
            ],
        ],
        'Chartjs' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.0/Chart.bundle.min.js',
                ],
            ],
        ],
        'Sweetalert2' => [
            'active' => true,   /* Activamos para todas las vistas de la plantilla Sweetalert2 */
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    /* 'location' => '//cdn.jsdelivr.net/npm/sweetalert2@8', */
                    'location' => 'vendor/sweetalert2/sweetalert2.all.min.js',
                ],
            ],
        ],
        'Pace' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/themes/blue/pace-theme-center-radar.min.css',
                ],
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Livewire support.
    |
    | For detailed instructions you can look the livewire here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/9.-Other-Configuration
    */

    'livewire' => false,
];
