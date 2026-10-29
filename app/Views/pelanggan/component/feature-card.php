<?php
/**
 * Komponen: kartu fitur "Kenapa pilih QuickBrew?".
 * Ikon digambar inline (garis, mengikuti warna teks); belum ada aset ikon di Figma.
 */
function feature_icon(string $name): string
{
    $paths = [
        'bolt'    => '<path d="M11 2 4 11h5l-1 7 8-9h-5z"/>',
        'sliders' => '<path d="M4 5h12M4 10h12M4 15h12"/><circle cx="7" cy="5" r="1.6" fill="#f1e5d8"/><circle cx="13" cy="10" r="1.6" fill="#f1e5d8"/><circle cx="8" cy="15" r="1.6" fill="#f1e5d8"/>',
        'cart'    => '<path d="M2.5 3h2l2 9h8l1.5-6.5H5.5"/><circle cx="8" cy="16" r="1"/><circle cx="14" cy="16" r="1"/>',
        'list'    => '<rect x="4" y="3" width="12" height="14" rx="2"/><path d="m7 8 1.2 1.2L10.5 7M7 13h6"/>',
    ];

    return '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

function render_feature_card(array $feature): void
{
    ?>
          <li class="feature">
            <span class="feature__icon"><?= feature_icon($feature['icon']) ?></span>
            <h3 class="feature__title"><?= e($feature['title']) ?></h3>
            <p class="feature__desc"><?= e($feature['description']) ?></p>
          </li>
<?php
}
