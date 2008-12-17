<?php
/*
+---------------------------------------------------------------+
| e107 website system
|
| �Steve Dunstan 2001-2002
| http://e107.org
| jalist@e107.org
|
| Released under the terms and conditions of the
| GNU General Public License (http://gnu.org).
|
| $Source: /cvs_backup/e107_0.8/e107_plugins/forum/forum_conf.php,v $
| $Revision: 1.5 $
| $Date: 2008-12-17 18:48:02 $
| $Author: mcfly_e107 $
+---------------------------------------------------------------+
*/
require_once('../../class2.php');
require_once(e_PLUGIN.'forum/forum_class.php');
$forum = new e107forum;

include_lan(e_PLUGIN.'forum/languages/English/lan_forum_conf.php');

$e_sub_cat = 'forum';

if(!USER || !isset($_GET['f']) || !isset($_GET['id']))
{
	header('location:'.$e107->url->getUrl('core:core', 'main', 'action=index'));
	exit;
}

$id = (int)$_GET['id'];
$action = $_GET['f'];

$qry = "
SELECT t.*, f.*, fp.forum_id AS forum_parent_id FROM #forum_t as t
LEFT JOIN #forum AS f ON t.thread_forum_id = f.forum_id
LEFT JOIN #forum AS fp ON fp.forum_id = f.forum_parent
WHERE t.thread_id = {$thread_id}
";

$threadInfo = $forum->threadGet($id);
$modList = $forum->forumGetMods($threadInfo->forum_moderators);

//var_dump($threadInfo);
//var_dump($modList);

//If user is not a moderator of indicated forum, redirect to index page
if(!in_array(USERID, array_keys($modList)))
{
	header('location:'.$e107->url->getUrl('core:core', 'main', 'action=index'));
	exit;
}

require_once(HEADERF);

if (isset($_POST['deletepollconfirm'])) {
	$sql->db_Delete("poll", "poll_id='".intval($thread_parent)."' ");
	$sql->db_Select("forum_t", "*", "thread_id='".$thread_id."' ");
	$row = $sql->db_Fetch();
	 extract($row);
	$thread_name = str_replace("[poll] ", "", $thread_name);
	$sql->db_Update("forum_t", "thread_name='$thread_name' WHERE thread_id='$thread_id' ");
	$message = FORCONF_5;
	$url = e_PLUGIN."forum/forum_viewtopic.php?".$thread_id;
}

if (isset($_POST['move']))
{
//	print_a($_POST);
	require_once(e_PLUGIN.'forum/forum_class.php');
	$forum = new e107forum;

	$postInfo = $forum->postGet($id, 0, 1);
	$threadId = (int)$postInfo[0]['post_thread'];
	$threadInfo = $forum->threadGet($threadId);

//	print_a($postInfo);
//	print_a($threadInfo);
//	exit;

	$oldForumId = (int)$threadInfo['thread_forum_id'];
	$newForumId = (int)$_POST['forum_move'];
	$newThreadName = $threadInfo['thread_name'];
	$replyCount = $threadInfo['thread_total_replies'];

	if($_POST['rename_thread'] == 'add')
	{
		$newThreadName = '['.FORCONF_27.'] '.$newThreadName;
	}
	elseif($_POST['rename_thread'] == 'rename' && trim($_POST['newtitle']) != '')
	{
		$newThreadName = $e107->tp->toDB($_POST['newtitle']);
	}

	//Move thread to new forum, changing thread title if needed
	$sql->db_Update('forum_thread', "thread_forum_id={$newForumId}, thread_name='{$newThreadName}' WHERE thread_id={$threadId}", true);

	//Move all posts to new forum
	$sql->db_Update('forum_post', "post_forum={$newForumId} WHERE post_thread={$threadId}", true);

	//set the thread counts correctly
	$sql->db_Update('forum', "forum_threads=forum_threads-1, forum_replies=forum_replies-$replyCount WHERE forum_id={$oldForumId}", true);
	$sql->db_Update('forum', "forum_threads=forum_threads+1, forum_replies=forum_replies+$replyCount WHERE forum_id={$newForumId}", true);

	// update lastpost information for old and new forums
	$forum->forumUpdateLastpost('forum', $oldForumId, false);
	$forum->forumUpdateLastpost('forum', $newForumId, false);

	$message = FORCONF_9;
//	$url = e_PLUGIN."forum/forum_viewforum.php?".$new_forum;
	$url = $e107->url->getUrl('forum', 'thread', 'func=view&id='.$id);

}

if (isset($_POST['movecancel']))
{
	require_once(e_PLUGIN.'forum/forum_class.php');
	$forum = new e107forum;
	$postInfo = $forum->postGet($id, 0, 1);

	$message = FORCONF_10;
//	$url = e_PLUGIN."forum/forum_viewforum.php?".$info['forum_id'];
	$url = $e107->url->getUrl('forum', 'forum', 'func=view&id='.$postInfo[0]['post_forum']);
}

if ($message)
{
	$text = "<div style='text-align:center'>".$message."
		<br />
		<a href='$url'>".FORCONF_11.'</a>
		</div>';
	$ns->tablerender(FORCONF_12, $text);
	require_once(FOOTERF);
	exit;
}

if ($action == "delete_poll")
{
	$text = "<div style='text-align:center'>
		".FORCONF_13."
		<br /><br />
		<form method='post' action='".e_SELF."?".e_QUERY."'>
		<input class='button' type='submit' name='deletecancel' value='".FORCONF_14."' />
		<input class='button' type='submit' name='deletepollconfirm' value='".FORCONF_15."' />
		</form>
		</div>";
	$ns->tablerender(FORCONF_16, $text);
	require_once(FOOTERF);
	exit;
}

if ($action == 'move')
{
	$postInfo = $forum->postGet($id, 0, 1);
	$text = "
		<form method='post' action='".e_SELF.'?'.e_QUERY."'>
		<div style='text-align:center'>
		<table style='".ADMIN_WIDTH."'>
		<tr>
		<td style='text-align:right'>".FORCONF_24.": </td>
		<td style='text-align:left'>
		<select name='forum_move' class='tbox'>";
	$qry = "
	SELECT f.forum_id, f.forum_name, fp.forum_name AS forum_parent, sp.forum_name AS sub_parent
	FROM `#forum` AS f
	LEFT JOIN `#forum` AS fp ON f.forum_parent = fp.forum_id
	LEFT JOIN `#forum` AS sp ON f.forum_sub = sp.forum_id
	WHERE f.forum_parent != 0
	AND f.forum_id != ".(int)$threadInfo['thread_forum_id']."
	ORDER BY f.forum_parent ASC, f.forum_sub, f.forum_order ASC
	";
	$e107->sql->db_Select_gen($qry);
	$fList = $e107->sql->db_getList();

	foreach($fList as $f)
	{
		if(substr($f['forum_name'], 0, 1) != '*')
		{
			$f['sub_parent'] = ltrim($f['sub_parent'], '*');
			$for_name = $f['forum_parent'].' -> ';
			$for_name .= ($f['sub_parent'] ? $f['sub_parent'].' -> ' : '');
			$for_name .= $f['forum_name'];
			$text .= "<option value='{$f['forum_id']}'>".$for_name."</option>";
		}
	}
	$text .= "</select>
		</td>
		</tr>
		<tr>
		<td colspan='2'><br />
		<b>".FORCONF_32."</b><br />
		<input type='radio' name='rename_thread' checked='checked' value='none' /> ".FORCONF_28."<br />
		<input type='radio' name='rename_thread' value='add' /> ".FORCONF_29.' ['.FORCONF_27.'] '.FORCONF_30."<br />
		<input type='radio' name='rename_thread' value='rename' /> ".FORCONF_31." <input type='text' class='tbox' name='newtitle' size='60' maxlength='250' value='".$tp->toForm($info['thread_name'])."'/>
		</td>
		</tr>
		<tr style='vertical-align: top;'>
		<td colspan='2'  style='text-align:center'><br />
		<input class='button' type='submit' name='move' value='".FORCONF_25."' />
		<input class='button' type='submit' name='movecancel' value='".FORCONF_14."' />
		</td>
		</tr>
		</table>
		</div>
		</form><br />";
	$text = $e107->ns->tablerender($e107->tp->toHTML($threadInfo['thread_name']), $e107->tp->toHTML($postInfo[0]['post_entry']), '', true).$ns->tablerender('', $text, '', true);
	$e107->ns->tablerender(FORCONF_25, $text);

}
require_once(FOOTERF);
?>