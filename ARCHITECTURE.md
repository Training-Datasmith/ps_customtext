# Architecture: ps_customtext

## Purpose

A PrestaShop module that displays a block of custom HTML text in a configurable widget
position. Supports per-language content and WYSIWYG editing in the back office.

## Directory Structure

```
ps_customtext.php   # Main module class (WidgetInterface)
views/templates/     # Smarty/Twig templates for text block display
translations/        # Translation files
tests/               # PHPStan and unit tests
```

## Key Design Decisions

Stores per-language custom text in PrestaShop's `Configuration` table using language-specific
keys. The back-office configuration page uses TinyMCE (the PS WYSIWYG editor) for rich
content editing. Implements `WidgetInterface` for flexible placement.

## Extension Points

Configure content per language in the module back-office settings.
Override template in theme for custom container styling.
