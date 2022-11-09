# PHP Etherpad Lite Client
This PHP Etherpad Lite class allows you to easily interact with Etherpad Lite API with PHP.  
Etherpad Lite is a collaborative editor provided by the Etherpad Foundation (http://etherpad.org)

## Basic usage

    include 'etherpad-lite-client.php';
    $instance = new EtherpadLiteClient('EtherpadFTW', 'http://beta.etherpad.org/api');
    $revisionCount = $instance->getRevisionsCount('testPad');
    $revisionCount = $revisionCount->revisions;
    echo "Pad has $revisionCount revisions\n\n";

## Examples

See it live here: http://beta.etherpad.org/example_big.php

Examples are located in examples.php and example_big.php.  
examples.php contains an example for each API call.  
example_big.php contains a UI for managing pads.

This example is the most commonly used, the example displays a pads text on the screen:

    $padContents = $instance->getText('testPad');
    echo "Pad text is: <br/><ul>$padContents->text\n\n</ul>";
    echo "End of Pad Text\n\n<hr>";

# License

Apache License

# Other stuff

The Etherpad Foundation also provides a jQuery plugin for Etherpad Lite.  
This can be found at http://etherpad.org/2011/08/14/etherpad-lite-jquery-plugin/
