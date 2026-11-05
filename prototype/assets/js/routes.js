export const ROUTES = {
  dashboard: 'dashboard/index.html',
  tournaments: 'tournaments/index.html',
  tournamentShow: 'tournaments/show.html',
  tournamentCreate: 'tournaments/create.html',
  submissions: 'tournaments/submissions.html',
  payments: 'tournaments/payments.html',
  teams: 'teams/index.html',
  teamShow: 'teams/show.html',
  teamRegister: 'teams/register.html',
  myTeam: 'teams/my-team.html',
  bracket: 'brackets/view.html',
  schedule: 'schedule/index.html',
  matches: 'schedule/matches.html',
  standings: 'standings/table.html',
  gameStats: 'games/stats.html',
  gameManage: 'games/manage.html',
  announcements: 'announcements/index.html',
  announcementCreate: 'announcements/create.html',
  profile: 'profile/index.html',
  audit: 'profile/audit.html',
  login: 'auth/login.html',
  register: 'auth/register.html',
  splash: 'auth/splash.html',
};

export function route(name, params = {}) {
  const path = ROUTES[name];
  if (!path) throw new Error(`Unknown route: ${name}`);
  const query = new URLSearchParams(
    Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== '')
  );
  return `../${path}${query.toString() ? `?${query.toString()}` : ''}`;
}