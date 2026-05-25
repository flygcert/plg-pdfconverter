<?php

/**
 * @package     PDF Converter
 *
 * @copyright   (C) 2007 - 2022 Flygcert FZE. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Flygcert\Plugin\Content\PDFConverter\Extension;

use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * PDF converter plugin for content.
 *
 * @since 4.0.0
 */
final class PDFConverter extends CMSPlugin implements SubscriberInterface
{
    /**
     * Cached Mpdf converter instance.
     *
     * @var   Mpdf|null
     * @since 5.0.0
     */
    private static ?Mpdf $converter = null;

    /**
     * Constructor.
     *
     * @param   array                $config      An optional associative array of configuration settings
     *
     * @since   4.0.0
     */
    public function __construct(array $config)
    {
        parent::__construct($config);

        $autoload = __DIR__ . '/../../vendor/autoload.php';

        // Initialize auto-loading.
        if (!file_exists($autoload)) {
            throw new \LogicException('Please run composer in pdfconverter plugin folder!');
        }

        require_once $autoload;
    }

    /**
     * @inheritDoc
     *
     * @return  string[]
     *
     * @since  4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPDFGenerate' => 'generatePDF',
        ];
    }

    /**
     * Listener for the `onPDFGenerate` event.
     *
     * @param   Event  $event  The 'onPDFGenerate' event.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function generatePDF(Event $event): void
    {
        $item = $event->getArgument('subject');
        $file = $event->getArgument('file');
        $path = $event->getArgument('path');

        if (!isset($item->css) && !isset($item->html)) {
            return;
        }

        try {
            $converter = $this->getConverter($item->params ?? []);

            $converter->SetCreator($this->getApplication()->get('sitename'));

            $converter->SetBasePath($this->params->get('base_path', Uri::root()));

            $converter->WriteHTML($item->css, HTMLParserMode::HEADER_CSS);
            $converter->WriteHTML($item->html, HTMLParserMode::HTML_BODY);

            if ($path) {
                $file = Path::clean($path . '/' . $file);

                $converter->Output($file, Destination::FILE);
            } else {
                $converter->Output($file, Destination::DOWNLOAD);
            }
        } catch (MpdfException $e) {
            throw new \Exception($e->getMessage(), 500, $e);
        }

        $event->setArgument('result', true);
    }

    /**
     * Get the mPDF converter.
     *
     * @param   ?array  $options  The options array.
     *
     * @return  Mpdf
     *
     * @since   4.0.0
     */
    public function getConverter(array $options = []): Mpdf
    {
        if (self::$converter !== null) {
            return self::$converter;
        }

        try {
            $options = new Registry($options);

            $config = [
                'mode'        => 'utf-8',
                'format'      => $options->get('page_format', 'A4') . '-' . $options->get('page_orientation', 'L'),
                'orientation' => $options->get('page_orientation', 'L'),
                'tempDir'     => JPATH_ROOT . '/tmp',
                'fontDir'     => [JPATH_ROOT . '/images/certificate/fonts'],
                'fontdata'    => [
                    'roboto' => [
                        'R' => 'roboto-regular-webfont.ttf',
                        'B' => 'roboto-bold-webfont.ttf',
                    ],
                ],
                'default_font' => 'roboto',
            ];

            if ($fonts = $this->params->get('custom_fonts')) {
                $config['fontdata'] += json_decode($fonts, true);
            }

            $mpdf = new Mpdf($config);

            if ($this->params->get('enable_logging', 0)) {
                $mpdf->setLogger(Log::createDelegatedLogger());
            }
        } catch (MpdfException $e) {
            throw new \Exception($e->getMessage(), 500, $e);
        }

        self::$converter = $mpdf;

        return self::$converter;
    }
}
