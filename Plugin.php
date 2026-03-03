<?php

namespace TypechoPlugin\Comment2Filter;

use Typecho\Cookie;
use Typecho\Db;
use Typecho\Db\Exception as DbException;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Exception as WidgetException;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Typecho\Widget\Helper\Form\Element\Text;
use Widget\Options;
use Widget\User;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 评论过滤器插件 for Typecho
 *
 * @package Comment2Filter
 * @author Vex
 * @version 2.8.0
 * @link https://github.com/vndroid/Comment2Filter
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return string
     */
    public static function activate(): string
    {
        \Typecho\Plugin::factory('Widget\Feedback')->comment = array(Plugin::class, 'filter');

        return _t('过滤器加载成功，请检查需要过滤的内容');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     */
    public static function deactivate(): void
    {
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Form $form 配置面板
     * @return void
     */
    public static function config(Form $form): void
    {
        $opt_visitor = new Radio('opt_visitor', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "none",
            _t('游客评论'), "如果评论发布者的未登录网站以游客身份评论，将执行该操作");
        $form->addInput($opt_visitor);


        $opt_ip = new Radio('opt_ip', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('屏蔽IP操作'), "如果评论发布者的IP在屏蔽IP段，将执行该操作");
        $form->addInput($opt_ip);
        $words_ip = new Textarea('words_ip', NULL, "0.0.0.0",
            _t('屏蔽IP'), _t('多条IP请用换行符隔开<br />支持用*号匹配IP段，如：192.168.*.*'));
        $form->addInput($words_ip);


        $opt_mail = new Radio('opt_mail', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('屏蔽邮箱操作'), "如果评论发布者的邮箱与禁止的一致，将执行该操作");
        $form->addInput($opt_mail);
        $words_mail = new Textarea('words_mail', NULL, "",
            _t('邮箱关键词'), _t('多个邮箱请用换行符隔开<br />可以是邮箱的全部，或者邮箱部分关键词'));
        $form->addInput($words_mail);


        $opt_url = new Radio('opt_url', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('屏蔽网址操作'), "如果评论发布者的网址与禁止的一致，将执行该操作。如果网址为空，该项不会起作用。");
        $form->addInput($opt_url);
        $words_url = new Textarea('words_url', NULL, "",
            _t('网址关键词'), _t('多个网址请用换行符隔开<br />可以是网址的全部，或者网址部分关键词。如果网址为空，该项不会起作用。'));
        $form->addInput($words_url);


        $opt_title = new Radio('opt_title', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('内容含有文章标题'), "如果评论内容中含有本页面的文章标题，则强行按该操作执行");
        $form->addInput($opt_title);


        $opt_au = new Radio('opt_au', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('屏蔽昵称关键词操作'), "如果评论发布者的昵称含有该关键词，将执行该操作");
        $form->addInput($opt_au);

        $words_au = new Textarea('words_au', NULL, "",
            _t('屏蔽昵称关键词'), _t('多个关键词请用换行符隔开'));
        $form->addInput($words_au);


        $au_length_min = new Text('au_length_min', NULL, '1', '昵称最短字符数', '昵称允许的最短字符数。');
        $au_length_min->input->setAttribute('class', 'mini');
        $form->addInput($au_length_min);
        $au_length_max = new Text('au_length_max', NULL, '15', '昵称最长字符数', '昵称允许的最长字符数');
        $au_length_max->input->setAttribute('class', 'mini');
        $form->addInput($au_length_max);
        $opt_au_length = new Radio('opt_au_length', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('昵称字符长度操作'), "如果昵称长度不符合条件，则强行按该操作执行。如果选择[无动作]，将忽略下面长度的设置");
        $form->addInput($opt_au_length);


        $opt_nojp_au = new Radio('opt_nojp_au', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('昵称日文操作'), "如果用户昵称中包含日文，则强行按该操作执行");
        $form->addInput($opt_nojp_au);

        $opt_nourl_au = new Radio('opt_nourl_au', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('昵称网址操作'), "如果用户昵称是网址，则强行按该操作执行");
        $form->addInput($opt_nourl_au);


        $opt_nojp = new Radio('opt_nojp', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('日文评论操作'), "如果评论中包含日文，则强行按该操作执行");
        $form->addInput($opt_nojp);


        $opt_nocn = new Radio('opt_nocn', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('非中文评论操作'), "如果评论中不包含中文，则强行按该操作执行");
        $form->addInput($opt_nocn);


        $length_min = new Text('length_min', NULL, '5', '评论最短字符数', '允许评论的最短字符数。');
        $length_min->input->setAttribute('class', 'mini');
        $form->addInput($length_min);
        $length_max = new Text('length_max', NULL, '200', '评论最长字符数', '允许评论的最长字符数');
        $length_max->input->setAttribute('class', 'mini');
        $form->addInput($length_max);
        $opt_length = new Radio('opt_length', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('评论字符长度操作'), "如果评论中长度不符合条件，则强行按该操作执行。如果选择[无动作]，将忽略下面长度的设置");
        $form->addInput($opt_length);


        $opt_ban = new Radio('opt_ban', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('禁止词汇操作'), "如果评论中包含禁止词汇列表中的词汇，将执行该操作");
        $form->addInput($opt_ban);

        $words_ban = new Textarea('words_ban', NULL, "fuck\n操你妈\n[url\n[/url]",
            _t('禁止词汇'), _t('多条词汇请用换行符隔开'));
        $form->addInput($words_ban);

        $opt_chk = new Radio('opt_chk', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('敏感词汇操作'), "如果评论中包含敏感词汇列表中的词汇，将执行该操作");
        $form->addInput($opt_chk);

        $words_chk = new Textarea('words_chk', NULL, "https://",
            _t('敏感词汇'), _t('多条词汇请用换行符隔开<br />注意：如果词汇同时出现于禁止词汇，则执行禁止词汇操作'));
        $form->addInput($words_chk);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Form $form
     * @return void
     */
    public static function personalConfig(Form $form): void
    {
    }

    /**
     * 评论过滤器
     *
     * @param array $comments 评论数据
     * @param mixed $post 文章内容对象
     * @param array $last 上一个插件的返回值（用于多插件链式调用）
     * @return array
     * @throws DbException
     * @throws WidgetException
     */
    public static function filter(array $comments, mixed $post, array $last): array
    {
        $comment = empty($last) ? $comments : $last;
        //提升同接口插件间的兼容性
        $options = Options::alloc();
        $user = User::alloc();
        $filter_set = $options->plugin(basename(__DIR__));
        $opt = "none";
        $error = "";

        //游客进行评论进行权限处理
        if ($opt == "none" && $filter_set->opt_visitor != "none" && !$user->hasLogin()) {
            $error = "对不起，本站暂时禁止游客进行评论！";
            $opt = $filter_set->opt_visitor;
        }

        //屏蔽评论内容包含文章标题
        if ($opt == "none" && $filter_set->opt_title != "none") {
            $db = Db::get();
            // 获取评论所在文章
            $po = $db->fetchRow($db->select('title')->from('table.contents')->where('cid = ?', $comment['cid']));
            if (strstr($comment['text'], $po['title'])) {
                $error = "对不起，评论内容不允许包含文章标题";
                $opt = $filter_set->opt_title;
            }
        }


        //屏蔽IP段处理
        if ($opt == "none" && $filter_set->opt_ip != "none") {
            if (Plugin::check_ip($filter_set->words_ip, $comment['ip'])) {
                $error = "评论发布者的IP已被管理员屏蔽";
                $opt = $filter_set->opt_ip;
            }
        }

        //屏蔽邮箱处理
        if ($opt == "none" && $filter_set->opt_mail != "none") {
            if (Plugin::check_in($filter_set->words_mail, $comment['mail'])) {
                $error = "评论发布者的邮箱地址被管理员屏蔽";
                $opt = $filter_set->opt_mail;
            }
        }

        //屏蔽网址处理
        if ($opt == "none" && $filter_set->opt_url != "none" && !empty($filter_set->words_url)) {
            if (Plugin::check_in($filter_set->words_url, $comment['url'])) {
                $error = "评论发布者的网址被管理员屏蔽";
                $opt = $filter_set->opt_url;
            }
        }

        //屏蔽昵称关键词处理
        if ($opt == "none" && $filter_set->opt_au != "none") {
            if (Plugin::check_in($filter_set->words_au, $comment['author'])) {
                $error = "对不起，昵称的部分字符已经被管理员屏蔽，请更换";
                $opt = $filter_set->opt_au;
            }
        }

        //日文评论处理
        if ($opt == "none" && $filter_set->opt_nojp != "none") {
            if (preg_match("/[\x{3040}-\x{31ff}]/u", $comment['text']) > 0) {
                $error = "禁止使用日文";
                $opt = $filter_set->opt_nojp;
            }
        }

        //日文用户昵称处理
        if ($opt == "none" && $filter_set->opt_nojp_au != "none") {
            if (preg_match("/[\x{3040}-\x{31ff}]/u", $comment['author']) > 0) {
                $error = "用户昵称禁止使用日文";
                $opt = $filter_set->opt_nojp_au;
            }
        }

        //昵称长度检测
        if ($opt == "none" && $filter_set->opt_au_length != "none") {
            if (Plugin::strLength($comment['author']) < $filter_set->au_length_min) {
                $error = "昵称请不得少于" . $filter_set->au_length_min . "个字符";
                $opt = $filter_set->opt_au_length;
            } else
                if (Plugin::strLength($comment['author']) > $filter_set->au_length_max) {
                    $error = "昵称请不得多于" . $filter_set->au_length_max . "个字符";
                    $opt = $filter_set->opt_au_length;
                }

        }

        //用户昵称网址判断处理
        if ($opt == "none" && $filter_set->opt_nourl_au != "none") {
            if (preg_match("/^((https?|ftp|news):\/\/)?([a-z]([a-z0-9\-]*[\.。])+([a-z]{2}|aero|arpa|biz|com|coop|edu|gov|info|int|jobs|mil|museum|name|nato|net|org|pro|travel)|(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))(\/[a-z0-9_\-\.~]+)*(\/([a-z0-9_\-\.]*)(\?[a-z0-9+_\-\.%=&]*)?)?(#[a-z][a-z0-9_]*)?$/", $comment['author']) > 0) {
                $error = "用户昵称不允许为网址";
                $opt = $filter_set->opt_nourl_au;
            }
        }

        //纯中文评论处理
        if ($opt == "none" && $filter_set->opt_nocn != "none") {
            if (preg_match("/[\x{4e00}-\x{9fa5}]/u", $comment['text']) == 0) {
                $error = "评论内容请不少于一个中文汉字";
                $opt = $filter_set->opt_nocn;
            }
        }

        //字符长度检测
        if ($opt == "none" && $filter_set->opt_length != "none") {
            if (Plugin::strLength($comment['text']) < $filter_set->length_min) {
                $error = "评论内容请不得少于" . $filter_set->length_min . "个字符";
                $opt = $filter_set->opt_length;
            } else
                if (Plugin::strLength($comment['text']) > $filter_set->length_max) {
                    $error = "评论内容请不得多于" . $filter_set->length_max . "个字符";
                    $opt = $filter_set->opt_length;
                }
        }

        //检查禁止词汇
        if ($opt == "none" && $filter_set->opt_ban != "none") {
            if (Plugin::check_in($filter_set->words_ban, $comment['text'])) {
                $error = "评论内容中包含禁止词汇";
                $opt = $filter_set->opt_ban;
            }
        }
        
        //检查敏感词汇
        if ($opt == "none" && $filter_set->opt_chk != "none") {
            if (Plugin::check_in($filter_set->words_chk, $comment['text'])) {
                $error = "评论内容中包含敏感词汇";
                $opt = $filter_set->opt_chk;
            }
        }

        //执行操作
        if ($opt == "abandon") {
            Cookie::set('__typecho_remember_text', $comment['text']);
            throw new WidgetException($error);
        } else if ($opt == "spam") {
            $comment['status'] = 'spam';
        } else if ($opt == "waiting") {
            $comment['status'] = 'waiting';
        }
        Cookie::delete('__typecho_remember_text');
        return $comment;
    }

    /**
     * 获取字符串长度（按 Unicode 字符计数）
     *
     * @param string $str
     * @return int
     */
    private static function strLength(string $str): int
    {
        preg_match_all('/./us', $str, $match);
        return count($match[0]);
    }

    /**
     * 检查字符串中是否含有限制词
     *
     * @param string $words_str
     * @param string $str
     * @return bool
     */
    private static function check_in(string $words_str, string $str): bool
    {
        if (empty(trim($words_str))) {
            return false;
        }
        $words = explode("\n", $words_str);
        foreach ($words as $word) {
            $word = trim($word);
            if ($word !== '' && str_contains($str, $word)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查IP是否在屏蔽IP段中
     *
     * @param string $words_ip
     * @param string $ip
     * @return bool
     */
    private static function check_ip(string $words_ip, string $ip): bool
    {
        if (empty(trim($words_ip))) {
            return false;
        }
        $words = explode("\n", $words_ip);
        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '') {
                continue;
            }
            if (str_contains($word, '*')) {
                $word = str_replace('.', '\.', $word);
                $word = "/^" . str_replace('*', '\d{1,3}', $word) . "$/";
                if (preg_match($word, $ip)) {
                    return true;
                }
            } else {
                if (str_contains($ip, $word)) {
                    return true;
                }
            }
        }
        return false;
    }
}
