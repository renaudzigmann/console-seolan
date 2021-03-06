<?php declare(strict_types=1);

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pho\Lib\Graph;

/**
 * For easy maintenance of TailNode and HeadNode classes.
 * 
 * Edges must have both source and target nodes that behave exactly the same.
 * This class is created to make the portability of TailNode and HeadNode
 * classes easy.
 * 
 * This class and its subclasses follow tbe 
 * {@link https://sourcemaking.com/design_patterns/proxy Proxy pattern}.
 * 
 * @see TailNode
 * @see HeadNode
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class AdjacentNode implements 
    NodeInterface, 
    EntityInterface
{
    private $instance;

    /**
     * Sets the node instance that this 
     *
     * @param  NodeInterface $node
     * @return void
     */
    public function set(NodeInterface $node): void
    {
        $this->instance = $node;
    }

    /**
     * Retrieves the node represented
     * 
     * In light of Proxy pattern, adjacent nodes represent 
     * an actual node, and pass any incoming method calls to 
     * that object. This function returns the actual node.
     *
     * @return NodeInterface
     */
    public function node(): NodeInterface
    {
        return $this->instance;
    }

    /**
     * @internal
     *
     * @param string $method
     * @param array  $arguments
     * @return void
     */
    public function __call(string $method, array $arguments) //: mixed 
    {
        $returns = call_user_func_array([$this->instance, $method], $arguments);
        return $returns; // will return null if void.
    }

}