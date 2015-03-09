<?php
/**
 * README.md example.
 * @author Joel Dalley
 * @version 2015/Mar/08
 */

// Simple inheritance heirarchry: Animal -> Mammal -> Primate.
class Animal                 { const VEGETABLE  = FALSE; public function move()  {} }
class Mammal  extends Animal { const HAS_HAIR   = TRUE;  public function shed()  {} }
class Primate extends Mammal { const HAS_THUMBS = TRUE;  public function grasp() {} }

require '../SimpleDocumenter.php';
$doc = new SimpleDocumenter('Primate');

// Only the nodes whose constants are defined in--or methods 
// and properties are declared in--the child class, 'Primate'.
$primate = function($node) { return $node->from() == 'Primate'; };

$pairs = array(
    'Primate defines the following constants' => $doc->constantNodes($primate),
    'All constants available in Primate'      => $doc->constantNodes(),
    'Primate declares the following methods'  => $doc->methodNodes($primate),
    'All methods available in Primate'        => $doc->methodNodes()
    );

foreach ($pairs as $phrase => $nodes) {
    print "$phrase: " . implode(', ', array_keys($nodes)) . "\n";
}
