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
$documenter = new SimpleDocumenter('Primate');

// Only the nodes whose constants are defined in / methods 
// & properties are declared in the child class, 'Primate'.
$primate = function($node) { return $node->from() == 'Primate'; };

echo "Primate defines the following constants: ",
     implode(', ', array_keys($documenter->constantNodes($primate))), "\n",
     "All constants available in Primate: ",
     implode(', ', array_keys($documenter->constantNodes())), "\n",
     "Primate declares the following methods: ",
     implode(', ', array_keys($documenter->methodNodes($primate))), "\n",
     "All methods available in Primate: ",
     implode(', ', array_keys($documenter->methodNodes())), "\n";
