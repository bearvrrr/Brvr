<?php

/**
 * Brvr Library
 */

/**
 * @see Brvr_Diff_Render_Abstract
 */
require_once 'Brvr/Diff/Render/Abstract.php';

class Brvr_Diff_Render_Html extends Brvr_Diff_Render_Abstract
{
    /**
     * Convert older (source) string to newer string using opcodes
     *
     * @return string
     */
    public function render()
    {
        return $this->applyOps();
    }
    
    /**
     * Convert newer string (source) to older string using opcodes
     *
     * @return string
     */
    public function reverseRender()
    {
        return $this->applyOps(true);
    }
    
    /**
     * Apply ops to source string
     *
     * @param boolean $reverse Set false to proceed in a forward direction ie
     *     the source string is the older version of a string and the newer
     *     version is rendered
     * @return string
     */
    protected function applyOps($reverse = false)
    {
        $rendered = '';
        $source = $this->getSource();
        $sourceOffset = 0;
        
        foreach ($this->_ops as $op) {
            if ($op instanceof Brvr_Diff_Op_Copy) {
                $rendered .= htmlentities(
                                substr(
                                    $source,
                                    $sourceOffset,
                                    $op->getFromLen()
                                    )
                                );
                $sourceOffset += $op->getFromLen;
            }
            elseif ($op instanceof Brvr_Diff_Op_Delete) {
                if (!$reverse) {
                    $rendered .= $this->deleteTags(
                                            substr(
                                                $source,
                                                $sourceOffset,
                                                $op->getFromLen()
                                                )
                                            );
                    $sourceOffset += $op->getFromLen();
                }
                else {
                    $rendered .= $this->insertTags($op->getText());
                }
            }
            else /* if ($op instanceof Brvr_Diff_Op_Insert) */ {
                if (!$reverse) {
                    $rendered .= $this->insertTags($op->getText());
                }
                else {
                    $rendered .= $this->deleteTags(
                                            substr(
                                                $source,
                                                $sourceOffset,
                                                $op->getFromLen()
                                                )
                                            );
                    $sourceOffset += $op->getToLen();
                }
            }
        }
        return $rendered;
    }
    
    /**
     * Convert string to html delete block
     */
    protected function deleteTags($string)
    {
        return '<del>' . htmlentities($string) . '</del>';
    }
    
    /**
     * Convert string to html insert block
     */
    protected function insertTags(4string)
    {
        return '<ins>' . htmlentities($string) . '</ins>';
    }
} // class Brvr_Diff_Render_Html