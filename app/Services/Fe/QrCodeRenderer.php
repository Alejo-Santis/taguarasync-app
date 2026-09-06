<?php

namespace App\Services\Fe;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeRenderer
{
    /**
     * Renderiza el contenido del QR de la DIAN (texto plano devuelto por Nextpyme
     * en `fe_qr_code`) como SVG inline. No depende de red ni de GD/Imagick, por lo
     * que funciona igual en el modo offline del POS.
     */
    public static function toSvg(string $data, int $size = 160): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 0),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($data);
    }
}
