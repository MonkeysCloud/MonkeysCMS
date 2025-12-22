<?php
echo "Context Class: " . get_class($this) . "\n";
if ($this instanceof \MonkeysLegion\Template\Renderer) {
    echo "Is Renderer: YES\n";
} else {
    echo "Is Renderer: NO\n";
}
