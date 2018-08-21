<?php
/**
 * Created by PhpStorm.
 * User: JiangFeng
 * Date: 2018/3/21
 * Time: 12:22
 */
class ElasticParser
{
    // 后序遍历：左子树 右子树 根节点
    public static function tailOrder($root)
    {
        $stack = [];
        $out = [];
        $stack[] = $root;
        while (!empty($stack)) {
            $node = array_pop($stack); //弹出最后一个，先进root->right->left，后出left->right->root
            $out[] = $node;
            if ($node->left) $stack[] = $node->left;
            if ($node->right) $stack[] = $node->right;
        }

        $data = [];
        while (!empty($out)) {
            $node = array_pop($out);
            $data[] = $node->value;
        }

        return $data;
    }


    /**
     * 解析
     *@param Node $node
     *@return array
     */
    public static function parser($node)
    {
        // 后序遍历
        $data = self::tailOrder($node);

        // 完全二叉树后序遍历
        // 遇到or/and，找到最后的两个chunk合并成一个chunk
        $chunks = [];
        foreach ($data as $k => $item) {
            if ($item === 'or' || $item === 'and') {
                $symbol = $item === 'or' ? 'should' : 'must';
                $chunk1 = array_pop($chunks);
                $chunk2 = array_pop($chunks);
                $new_chunk = [$chunk1, $chunk2];
                $chunks[]['bool'][$symbol] = $new_chunk;
            }

            if ($item === '=' || $item === 'like') {
                $chunks[] = self::handleChunk([$data[$k-2], $item, $data[$k-1]]);
            }
        }

//        debug(json_encode_ex($chunks));
        return $chunks;

    }

    /**
     * 处理chunk
     *@param array $chunk
     *@return array
     */
    public static function handleChunk($chunk)
    {
        if ($chunk[1] === '=') {
            return ['term' => [$chunk[0] => $chunk[2]]];
        } else if ($chunk[1] === 'like') {
            if (is_array($chunk[2])) {
                $d = [];
                foreach ($chunk[2] as $word) {
                    $d[] = ['multi_match' => [
                        'query' => $word,
                        'type' => 'phrase',
                        'fields' => $chunk[0]],
                    ];
                }
                return ['bool' => ['should' => $d]];
            } else {
                return ['multi_match' => [
                    'query' => $chunk[2],
                    'type' => 'phrase',
                    'fields' => $chunk[0]],
                ];
            }
        }

        return [];
    }

    public static function test()
    {
//        $a = new Node();
//        $b = new Node();
//        $c = new Node();
//        $d = new Node();
//        $e = new Node();
//        $f = new Node();
//        $a->value = 'A';
//        $b->value = 'B';
//        $c->value = 'C';
//        $d->value = 'D';
//        $e->value = 'E';
//        $f->value = 'F';
//        $a->left = $b;
//        $a->right = $c;
//        $b->left = $d;
//        $c->left = $e;
//        $c->right = $f;
//        self::tailOrder($a);

        // (entity_type=A1 and ((title like 'a') or (title like 'b') or (title like 'c') or community_channel_id = 2))
        // or
        // (entity_type=B1 and tag = 1)
        $a = new Node();
        $b = new Node();
        $c = new Node();
        $d = new Node();
        $e = new Node();
        $f = new Node();
        $g = new Node();
        $h = new Node();
        $i = new Node();
        $j = new Node();
        $k = new Node();
        $l = new Node();
        $m = new Node();
        $n = new Node();
        $o = new Node();
        $p = new Node();
        $q = new Node();
        $r = new Node();
        $s = new Node();
        $a->value = 'or';
        $b->value = 'and';
        $c->value = 'and';
        $d->value = '=';
        $e->value = 'or';
        $f->value = '=';
        $g->value = '=';
        $h->value = 'entity_type';
        $i->value = 'CommunityThread';
        $j->value = 'like';
        $k->value = '=';
        $l->value = 'entity_type';
        $m->value = 'QaQuestion';
        $n->value = 'tag';
        $o->value = '1';
        $p->value = 'match_title';
        $q->value = ['清单', '攻略', '流程'];
        $r->value = 'channel_id';
        $s->value = '3';

        $a->left = $b;
        $a->right = $c;
        $b->left = $d;
        $b->right = $e;
        $c->left = $f;
        $c->right = $g;
        $d->left = $h;
        $d->right = $i;
        $e->left = $j;
        $e->right = $k;
        $f->left = $l;
        $f->right = $m;
        $g->left = $n;
        $g->right = $o;
        $j->left = $p;
        $j->right = $q;
        $k->left = $r;
        $k->right = $s;
        self::tailOrder($a);
    }
}
