<?php

// Quantities are requirements per package, never opening warehouse balances.
return [
    'inventory' => [
        'probooth' => ['name' => 'Probooth', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'camera_700d' => ['name' => 'Camera 700D', 'aliases' => ['Kamera 700D', 'Canon 700D'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'dummy_700d' => ['name' => 'Dummy 700D', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'mini_pc' => ['name' => 'Device Mini PC', 'aliases' => ['Mini PC'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'softbox_p120' => ['name' => 'Softbox P120', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'sl300' => ['name' => 'Lighting SL 300', 'aliases' => ['Lighting SL300'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'lighting_stand' => ['name' => 'Stand Lighting', 'aliases' => ['Takara'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'camera_mount' => ['name' => 'Mounting Camera', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'printer_dnp' => ['name' => 'Printer DNP', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'ribbon' => ['name' => 'Ribbon', 'aliases' => ['Ribon'], 'tracking_type' => 'quantity', 'unit' => 'roll'],
        'cable_roll' => ['name' => 'Kabel Roll', 'aliases' => ['Perleng', 'Kabel Rol'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'keyboard_mouse' => ['name' => 'Keyboard + Mouse', 'aliases' => ['Keyboard Mouse'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'tl120' => ['name' => 'TL 120', 'aliases' => ['TL120'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'monitor_24' => ['name' => 'Monitor 24 inch ViewSonic', 'aliases' => ['Monitor 24" Viewsonic', 'Monitor 24" Viewsonec'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'curtain_pole' => ['name' => 'Tiang Gorden', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'red_curtain' => ['name' => 'Gorden Merah', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'magic_arm' => ['name' => 'Magic Arm', 'aliases' => ['Clamp Arm'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'c_stand' => ['name' => 'C-Stand', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'wide_lens' => ['name' => 'Lensa Wide', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'lantern' => ['name' => 'Softbox Lantern', 'aliases' => ['Softbox Lentern'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'ribbon_corporated' => ['name' => 'Ribbon Corporated', 'aliases' => ['Ribon Corporated'], 'tracking_type' => 'quantity', 'unit' => 'roll'],
        'tv_43' => ['name' => 'TV 43 inch', 'aliases' => ['TV 43"', 'TV43"'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'tv_stand' => ['name' => 'Stand TV', 'tracking_type' => 'serialized', 'unit' => 'unit', 'notes' => 'Default Tripod TV. Boleh ganti ke Stand TV Cart pada Surat Jalan sebelum alokasi.'],
        'cutter' => ['name' => 'Pemotong', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'laminator' => ['name' => 'Mesin Laminating', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        // Lenticular printing material, distinct from a reusable camera lens.
        'hologram_lens' => ['name' => 'Lensa Hologram', 'aliases' => ['Lensa Lenticular', 'Lenticular'], 'tracking_type' => 'quantity', 'unit' => 'lembar', 'notes' => 'Satu lembar lenticular per hasil cetak. Jumlah kebutuhan mengikuti pesanan.'],
        'monopod' => ['name' => 'Monopod', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'battery_700d' => ['name' => 'Baterai Cas 700D', 'aliases' => ['Baterai 700D'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'flash' => ['name' => 'Flash', 'aliases' => ['Flash YN 560 III'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'charger_700d' => ['name' => 'Casan Baterai 700D', 'aliases' => ['Charger Baterai 700D'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'laptop' => ['name' => 'Device Laptop', 'aliases' => ['Laptop'], 'tracking_type' => 'serialized', 'unit' => 'unit', 'notes' => 'Pilih laptop tersedia pada Surat Jalan: Legion, LOQ, LOQ RRQ, HP, atau MSI; lalu pilih/scan unit QR saat alokasi.'],
        'ipad' => ['name' => 'iPad', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'magic_clamp' => ['name' => 'Magic Clamp', 'aliases' => ['Clamp Kecil'], 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'white_background' => ['name' => 'Background Putih', 'tracking_type' => 'serialized', 'unit' => 'unit'],
        'background_pole' => ['name' => 'Tiang Background', 'tracking_type' => 'serialized', 'unit' => 'unit'],
    ],
    'templates' => [
        'classic' => [
            'name' => 'Classic', 'product_names' => ['Classic', 'Classic Photobooth', 'Photobooth Classic'],
            'items' => ['probooth' => 1, 'camera_700d' => 1, 'dummy_700d' => 1, 'mini_pc' => 1, 'softbox_p120' => 2, 'sl300' => 2, 'lighting_stand' => 2, 'camera_mount' => 1, 'printer_dnp' => 1, 'ribbon' => 2, 'cable_roll' => 3, 'keyboard_mouse' => 1],
        ],
        'classic_box' => [
            'name' => 'Classic Box', 'product_names' => ['Classic Box', 'Classic Box Photobooth'],
            'items' => ['camera_700d' => 1, 'dummy_700d' => 1, 'mini_pc' => 1, 'tl120' => 1, 'monitor_24' => 1, 'curtain_pole' => 1, 'red_curtain' => 1, 'magic_arm' => 1, 'printer_dnp' => 1, 'ribbon' => 2, 'cable_roll' => 3, 'keyboard_mouse' => 1],
        ],
        'high_angle' => [
            'name' => 'High Angle', 'product_names' => ['High Angle', 'High Angel', 'Highangle Photobooth', 'High Angle Photobooth'],
            'items' => ['c_stand' => 1, 'camera_700d' => 1, 'dummy_700d' => 1, 'wide_lens' => 1, 'mini_pc' => 1, 'lantern' => 1, 'sl300' => 1, 'magic_arm' => 1, 'printer_dnp' => 1, 'ribbon' => 2, 'cable_roll' => 3, 'keyboard_mouse' => 1, 'monitor_24' => 1],
        ],
        'high_angle_box' => [
            'name' => 'High Angle Box', 'product_names' => ['High Angle Box', 'High Angel Box', 'Highangle Box Photobooth'],
            'items' => ['c_stand' => 1, 'camera_700d' => 1, 'dummy_700d' => 1, 'mini_pc' => 1, 'lantern' => 1, 'sl300' => 1, 'magic_arm' => 1, 'printer_dnp' => 1, 'monitor_24' => 1, 'ribbon' => 2, 'cable_roll' => 3, 'keyboard_mouse' => 1],
        ],
        'mozaik' => [
            'name' => 'Mozaik', 'product_names' => ['Mozaik', 'Mozaik Photobooth'],
            'items' => ['c_stand' => 1, 'camera_700d' => 1, 'dummy_700d' => 1, 'wide_lens' => 1, 'mini_pc' => 1, 'lantern' => 1, 'sl300' => 1, 'magic_arm' => 1, 'printer_dnp' => 1, 'ribbon_corporated' => 2, 'cable_roll' => 3, 'keyboard_mouse' => 1, 'monitor_24' => 1],
        ],
        'hologram' => [
            'name' => 'Hologram', 'product_names' => ['Hologram', 'Hologram Photobooth'],
            'item_options' => ['hologram_lens' => ['quantity_basis' => 'manual']],
            'items' => ['c_stand' => 1, 'camera_700d' => 1, 'dummy_700d' => 1, 'wide_lens' => 1, 'mini_pc' => 1, 'lantern' => 1, 'sl300' => 1, 'tv_43' => 1, 'tv_stand' => 1, 'magic_arm' => 1, 'printer_dnp' => 1, 'ribbon' => 2, 'cable_roll' => 3, 'keyboard_mouse' => 1, 'cutter' => 1, 'laminator' => 1, 'hologram_lens' => 1, 'monitor_24' => 1],
        ],
        'take_me_away' => [
            'name' => 'Take Me Away', 'product_names' => ['Take Me Away', 'Take Me Away Photobooth'],
            'items' => ['monopod' => 1, 'camera_700d' => 1, 'battery_700d' => 2, 'flash' => 1, 'charger_700d' => 1, 'laptop' => 1, 'ipad' => 1, 'magic_clamp' => 1, 'magic_arm' => 1, 'printer_dnp' => 1, 'ribbon' => 2, 'cable_roll' => 3],
        ],
        'ai_generative' => [
            'name' => 'AI Generative', 'product_names' => ['AI Generative', 'AI Generative Photobooth'],
            'items' => ['camera_700d' => 1, 'dummy_700d' => 1, 'white_background' => 1, 'background_pole' => 1, 'laptop' => 1, 'softbox_p120' => 2, 'sl300' => 2, 'lighting_stand' => 5, 'magic_arm' => 1, 'printer_dnp' => 1, 'ribbon' => 2, 'cable_roll' => 3, 'tv_43' => 1, 'tv_stand' => 1],
        ],
        // Optional sale add-on: never add this lens to other base packages.
        'additional_hologram_lens' => [
            'name' => 'Additional Lensa Hologram', 'optional' => true,
            'product_names' => ['Additional Lensa Hologram', 'Additional Hologram Lens'],
            'items' => ['hologram_lens' => 1],
            'item_options' => ['hologram_lens' => ['quantity_basis' => 'sales']],
        ],
    ],
];
