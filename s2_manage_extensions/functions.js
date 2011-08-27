/**
 * Manage extensions
 *
 * Client-side functions.
 *
 * @copyright (C) 2009-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_manage_extensions
 */


function emRefreshHooks (sId)
{
	GETSyncRequest(sUrl + "action=s2_manage_extensions_refresh_hooks&id=" + sId);

	return false;
}
