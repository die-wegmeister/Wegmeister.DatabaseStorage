<?php

namespace Wegmeister\DatabaseStorage\ViewHelpers;

/*
 * This file is part of the Neos.FluidAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3Fluid\Fluid\Core\Compiler\TemplateCompiler;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;

/**
 * Helper to replace links in text.
 *
 * = Examples =
 *
 * <code title="Example">
 * {namespace dbs=Wegmeister\DatabaseStorage\ViewHelpers}
 * <dbs:formatUris>{text_with_links}</dbs:formatUris>
 * </code>
 * <output>
 * links wrapped by <a href="https://link" target="_blank" rel="noopener noreferrer">https://link</a>
 * </output>
 *
 * <code title="Inline notation">
 * {namespace dbs=Wegmeister\DatabaseStorage\ViewHelpers}
 * {text_with_links -> dbs:formatUris()}
 * </code>
 * <output>
 * links wrapped by <a href="https://link" target="_blank" rel="noopener noreferrer">https://link</a>
 * </output>
 */
class FormatUrisViewHelper extends AbstractViewHelper
{
    /**
     * Regex to find links
     */
    protected static $uriPattern = '/^(https?):\/\/' .              // protocol
        '(([a-z0-9$_\.\+!\*\'\(\),;\?&=-]|%[0-9a-f]{2})+' .         // username
        '(:([a-z0-9$_\.\+!\*\'\(\),;\?&=-]|%[0-9a-f]{2})+)?' .      // password
        '@)?(?#' .                                                  // auth requires @
        ')((([a-z0-9]\.|[a-z0-9][a-z0-9-]*[a-z0-9]\.)*' .           // domain segments AND
        '[a-z][a-z0-9-]*[a-z0-9]' .                                 // top level domain  OR
        '|((\d|[1-9]\d|1\d{2}|2[0-4][0-9]|25[0-5])\.){3}' .
        '(\d|[1-9]\d|1\d{2}|2[0-4][0-9]|25[0-5])' .                 // IP address
        ')(:\d+)?' .                                                // port
        ')(((\/+([a-z0-9$_\.\+!\*\'\(\),;:@&=-]|%[0-9a-f]{2})*)*' . // path
        '(\?([a-z0-9$_\.\+!\*\'\(\),;:@&=-]|%[0-9a-f]{2})*)' .      // query string
        '?)?)?' .                                                   // path and query string optional
        '(#([a-z0-9$_\.\+!\*\'\(\),;:@&=-]|%[0-9a-f]{2})*)?' .      // fragment
        '$/i';

    /**
     * @var boolean
     */
    protected $escapeOutput = false;

    /**
     * Initialize the arguments.
     *
     * @return void
     * @api
     */
    public function initializeArguments()
    {
        $this->registerArgument('value', 'string', 'string to format', false, null);
    }

    /**
     * Replaces links by HTML links.
     *
     * @return string the altered string.
     * @api
     */
    public function render()
    {
        $value = $this->arguments['value'];

        if ($value === null) {
            $value = $this->renderChildren();
        }

        return preg_replace(self::$uriPattern, '<a href="$0" target="_blank" rel="noopener noreferrer">$0</a>', $value);
    }

    /**
     * Compile to direct link replacement use in template code.
     *
     * @param string $argumentsName
     * @param string $closureName
     * @param string $initializationPhpCode
     * @param ViewHelperNode $node
     * @param TemplateCompiler $compiler
     * @return string
     */
    public function compile($argumentsName, $closureName, &$initializationPhpCode, ViewHelperNode $node, TemplateCompiler $compiler)
    {
        $valueVariableName = $compiler->variableName('value');
        $initializationPhpCode .= sprintf('%1$s = (%2$s[\'value\'] !== null ? %2$s[\'value\'] : %3$s());', $valueVariableName, $argumentsName, $closureName) . chr(10);

        return sprintf(
            'preg_replace(\'%1$s\', \'<a href="$0" target="_blank" rel="noopener noreferrer">$0</a>\', %2$s)',
            str_replace("'", "\\'", self::$uriPattern),
            $valueVariableName
        );
    }
}
