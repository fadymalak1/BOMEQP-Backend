<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Set some default values. It is possible to add all defines that can be set
    | in dompdf_config.inc.php. You can also override the entire config file.
    |
    */
    'show_warnings' => false,   // Throw an Exception on warnings from dompdf
    'public_path' => null, // Override the public path if needed

    /*
     * Dejavu Sans font is missing glyphs for converted entities, turn it off if you need to show € and £.
     */
    'convert_entities' => true,

    'options' => [
        /*
         * The location of the DOMPDF font directory
         *
         * The location of where the DOMPDF font directory exists. The default is the
         * included fonts directory. If you install fonts outside of this directory,
         * you must update the path below.
         */
        'font_dir' => storage_path('fonts'), // advised by dompdf

        /*
         * The location of the DOMPDF font cache directory
         *
         * This directory contains the cached font metrics for the fonts used by DOMPDF.
         * This directory can be the same as the font directory.
         */
        'font_cache' => storage_path('fonts'),
        
        /*
         * The font directory is also checked for PHP GD image rendering
         * Make sure fonts are available in: resources/fonts/ or storage/fonts/
         */

        /*
         * The location of a temporary directory.
         *
         * The directory specified must be writeable by the webserver process.
         * The temporary directory is required to download remote images and when
         * using the PFDLib back end.
         */
        'temp_dir' => sys_get_temp_dir(),

        /*
         * ==== IMPORTANT ====
         *
         * dompdf's "chroot": Prevents dompdf from accessing system files or other
         * files on the webserver.  All local files opened by dompdf must be in a
         * subdirectory of this directory.  DO NOT set it to '/' since this could
         * allow an attacker to use dompdf to read files like /etc/passwd
         */
        'chroot' => realpath(base_path()),

        /*
         * Whether to enable font subsetting or not.
         */
        'enable_font_subsetting' => false,

        /*
         * The PDF rendering backend to use
         *
         * Valid settings are 'PDFLib', 'CPDF' (the bundled R&OS PDF class), 'GD' or
         * 'auto'. 'auto' will look for PDFLib and use it if found, or if not it will
         * fall back on CPDF. 'GD' renders PDFs to graphic files. {@link
         * Canvas_Factory} ultimately determines which rendering class to instantiate
         * based on this setting.
         *
         * Both PDFLib & CPDF rendering backends provide sufficient rendering
         * capabilities for dompdf, however additional features (e.g. object,
         * image and font support, etc.) differ between backends.  Please see
         * {@link PDFLib_Adapter} for more information on the PDFLib backend
         * and {@link CPDF_Adapter} and {@link CPDF_Canvas} for more information
         * on CPDF.
         *
         * The GD rendering backend is a little different than PDFLib and CPDF.
         * Several features of CPDF and PDFLib are not supported or do not make any
         * sense when creating image files.  For example, multiple pages are not
         * supported, nor are PDF 'objects'.  Have a look at {@link GD_Adapter} for
         * more information.  GD support is experimental, so use it at your own risk.
         *
         * @var string
         */
        'pdf_backend' => 'CPDF',

        /*
         * PDFlib license key
         *
         * If you are using a licensed, commercial version of PDFlib, specify
         * your license key here.  If you are using PDFlib-Lite or are evaluating
         * PDFlib, comment out this line.
         *
         * @link http://www.pdflib.com
         *
         * @var string
         */
        //"pdflib_license" => "your license key here",

        /*
         * html target media view which should be rendered into pdf.
         * List of types and parsing rules for future extensions:
         * http://www.w3.org/TR/REC-html40/types.html
         *   screen, tty, tv, projection, handheld, print, braille, aural, all
         * Note: aural is deprecated in CSS 2.1 because it is replaced by speech in CSS 3.
         * Note, even though the generated pdf file is intended for print output,
         * the desired content might be different (e.g. screen or projection view of html file).
         * Therefore allow specification of content here.
         */
        'default_media_type' => 'screen',

        /*
         * The default paper size.
         *
         * North America standard is "letter"; other countries generally "a4"
         *
         * @see CPDF_Adapter::PAPER_SIZES for valid sizes ('letter', 'legal', 'A4', etc.)
         */
        'default_paper_size' => 'a4',

        /*
         * The default paper orientation.
         *
         * The orientation of the page (portrait or landscape).
         *
         * @see CPDF_Adapter::ORIENTATIONS for valid orientations ('portrait', 'landscape')
         */
        'default_paper_orientation' => 'portrait',

        /*
         * The default font family
         *
         * Used if no suitable fonts can be found. This must exist in the font folder.
         * @var string
         */
        'default_font' => 'serif',

        /*
         * Image DPI setting
         *
         * This setting determines the default DPI setting for images and fonts.  The
         * DPI may be overridden for inline images by explictly setting the
         * image's width and height style attributes (i.e. if the image's native
         * width is 600 pixels and you specify the image's width as 72 points,
         * the image will have a DPI of 600 in the rendered PDF.
         *
         * @var int
         */
        'dpi' => 96,

        /*
         * The default font size
         *
         * @var int
         */
        'font_height_ratio' => 1.1,

        /*
         * Enable embedded PHP
         *
         * If this setting is set to true then DOMPDF will automatically evaluate
         * embedded PHP contained within <script type="text/php"> ... </script> tags.
         *
         * Enabling this for documents you do not trust (e.g. arbitrary remote html
         * pages) is a security risk.  Set this option to false if you wish to process
         * untrusted documents.
         *
         * @var bool
         */
        'enable_php' => false,

        /*
         * Enable inline Javascript
         *
         * If this setting is set to true then DOMPDF will automatically insert
         * JavaScript code contained within <script type="text/javascript"> ... </script> tags.
         *
         * @var bool
         */
        'enable_javascript' => true,

        /*
         * Enable remote file access
         *
         * If this setting is set to true, DOMPDF will access remote sites for
         * images and CSS files as required.
         * This is required for part of test case www/test/image_variants.html through www/examples.php
         *
         * @var bool
         */
        'is_remote_enabled' => true,

        /*
         * A ratio applied to the fonts height to be more like browsers' line height
         */
        'is_font_subsetting_enabled' => false,

        /*
         * Use the HTML5 Lib parser
         *
         * @deprecated This feature is now always on in dompdf
         * @var bool
         */
        'is_html5_parser_enabled' => true,

        /*
         * Whether to enable CSS float
         *
         * Allows people to disable CSS float support, primarily for backwards compatibility.
         *
         * @var bool
         */
        'enable_css_float' => false,

        /*
         * Enable inline CSS
         *
         * This allows users to enable inline CSS. When disabled, `<style>` tags will be ignored.
         */
        'enable_inline_css' => true,
    ],

];

