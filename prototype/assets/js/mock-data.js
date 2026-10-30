/**
 * HoopFlow Mock Database & State Store
 * Persists in localStorage for interactive testing and demos.
 */

const DEFAULT_STORE = {
  currentRole: 'organizer', // 'organizer' | 'coach' | 'fan'
  currentUser: {
    name: 'Coach Marcus Rivera',
    email: 'coach.marcus@hoopflow.ph',
    role: 'coach',
    organization: 'Barangay San Antonio Youth Club',
    teamId: 'team-1'
  },
  tournaments: [
    {
      id: 'tourney-1',
      name: 'Barangay Summer Youth Cup 2026',
      division: 'Under 18 (U18)',
      type: 'Single Elimination',
      slots: 8,
      registeredCount: 8,
      dates: 'May 15 - May 22, 2026',
      venue: 'San Antonio Central Sports Complex',
      courts: ['Court 1 (Main Gym)', 'Court 2 (Outdoor Covered)'],
      status: 'Active',
      description: 'Annual inter-barangay grassroots youth tournament. Pure single-elimination tournament with accredited SBP referees.'
    },
    {
      id: 'tourney-2',
      name: 'Metro Manila High School Open',
      division: 'Under 16 (U16)',
      type: 'Single Elimination',
      slots: 16,
      registeredCount: 12,
      dates: 'June 01 - June 10, 2026',
      venue: 'Ynares Sports Arena, Pasig',
      courts: ['Court A', 'Court B'],
      status: 'Registration Open',
      description: 'Pre-season invitational tournament for competitive secondary school basketball teams.'
    },
    {
      id: 'tourney-3',
      name: 'Cagayan de Oro Grassroots Classic',
      division: 'Under 14 (U14)',
      type: 'Round Robin + Playoffs',
      slots: 8,
      registeredCount: 6,
      dates: 'July 10 - July 18, 2026',
      venue: 'CDO City Coliseum',
      courts: ['Main Court'],
      status: 'Upcoming',
      description: 'Regional developmental tournament focusing on fundamental basketball and youth sportsmanship.'
    }
  ],
  teams: [
    {
      id: 'team-1',
      name: 'San Antonio Vipers',
      shortName: 'VPR',
      coach: 'Marcus Rivera',
      city: 'Pasig City',
      wins: 4,
      losses: 0,
      winStreak: 4,
      lossStreak: 0,
      pointsDiff: '+42',
      status: 'Approved',
      paymentStatus: 'Paid',
      paymentRef: 'GCASH-9823412',
      avatarColor: '#ea580c'
    },
    {
      id: 'team-2',
      name: 'Kapitolyo Knights',
      shortName: 'KNT',
      coach: 'Danilo Santos',
      city: 'Pasig City',
      wins: 3,
      losses: 1,
      winStreak: 2,
      lossStreak: 0,
      pointsDiff: '+28',
      status: 'Approved',
      paymentStatus: 'Paid',
      paymentRef: 'MAYA-4412093',
      avatarColor: '#2563eb'
    },
    {
      id: 'team-3',
      name: 'Mandaluyong Tigers',
      shortName: 'TGR',
      coach: 'Ramon Bautista',
      city: 'Mandaluyong City',
      wins: 3,
      losses: 1,
      winStreak: 1,
      lossStreak: 0,
      pointsDiff: '+19',
      status: 'Approved',
      paymentStatus: 'Paid',
      paymentRef: 'CASH-REC-001',
      avatarColor: '#d97706'
    },
    {
      id: 'team-4',
      name: 'Ortigas Raptors',
      shortName: 'RPT',
      coach: 'Kenneth Cruz',
      city: 'Pasig City',
      wins: 2,
      losses: 2,
      winStreak: 0,
      lossStreak: 1,
      pointsDiff: '+6',
      status: 'Approved',
      paymentStatus: 'Paid',
      paymentRef: 'BANK-BDO-771',
      avatarColor: '#7c3aed'
    },
    {
      id: 'team-5',
      name: 'Ugong Blue Hawks',
      shortName: 'HWK',
      coach: 'Allan Gomez',
      city: 'Pasig City',
      wins: 1,
      losses: 3,
      winStreak: 0,
      lossStreak: 2,
      pointsDiff: '-14',
      status: 'Approved',
      paymentStatus: 'Pending',
      paymentRef: 'Unpaid',
      avatarColor: '#0284c7'
    },
    {
      id: 'team-6',
      name: 'Maybunga Titans',
      shortName: 'TTN',
      coach: 'Eric Villanueva',
      city: 'Pasig City',
      wins: 1,
      losses: 3,
      winStreak: 0,
      lossStreak: 1,
      pointsDiff: '-22',
      status: 'Approved',
      paymentStatus: 'Paid',
      paymentRef: 'GCASH-1123498',
      avatarColor: '#059669'
    },
    {
      id: 'team-7',
      name: 'Rosario Strikers',
      shortName: 'STR',
      coach: 'Joel Macaraeg',
      city: 'Pasig City',
      wins: 0,
      losses: 4,
      winStreak: 0,
      lossStreak: 4,
      pointsDiff: '-35',
      status: 'Pending',
      paymentStatus: 'Pending',
      paymentRef: 'Pending Review',
      avatarColor: '#dc2626'
    },
    {
      id: 'team-8',
      name: 'Pinagbuhatan Warriors',
      shortName: 'WRR',
      coach: 'Ferdinand Reyes',
      city: 'Pasig City',
      wins: 0,
      losses: 4,
      winStreak: 0,
      lossStreak: 4,
      pointsDiff: '-24',
      status: 'Pending',
      paymentStatus: 'Pending',
      paymentRef: 'Pending Review',
      avatarColor: '#475569'
    }
  ],
  bracketMatches: [
    // Quarterfinals
    { id: 'qf-1', round: 'Quarterfinals', team1: 'San Antonio Vipers', team2: 'Pinagbuhatan Warriors', score1: 78, score2: 61, winner: 'San Antonio Vipers', court: 'Court 1', time: 'May 16 - 9:00 AM', status: 'Completed' },
    { id: 'qf-2', round: 'Quarterfinals', team1: 'Ortigas Raptors', team2: 'Ugong Blue Hawks', score1: 69, score2: 64, winner: 'Ortigas Raptors', court: 'Court 2', time: 'May 16 - 10:30 AM', status: 'Completed' },
    { id: 'qf-3', round: 'Quarterfinals', team1: 'Kapitolyo Knights', team2: 'Rosario Strikers', score1: 82, score2: 55, winner: 'Kapitolyo Knights', court: 'Court 1', time: 'May 16 - 1:00 PM', status: 'Completed' },
    { id: 'qf-4', round: 'Quarterfinals', team1: 'Mandaluyong Tigers', team2: 'Maybunga Titans', score1: 71, score2: 68, winner: 'Mandaluyong Tigers', court: 'Court 2', time: 'May 16 - 2:30 PM', status: 'Completed' },
    // Semifinals
    { id: 'sf-1', round: 'Semifinals', team1: 'San Antonio Vipers', team2: 'Ortigas Raptors', score1: 74, score2: 70, winner: 'San Antonio Vipers', court: 'Court 1', time: 'May 18 - 1:00 PM', status: 'Completed' },
    { id: 'sf-2', round: 'Semifinals', team1: 'Kapitolyo Knights', team2: 'Mandaluyong Tigers', score1: 80, score2: 76, winner: 'Kapitolyo Knights', court: 'Court 1', time: 'May 18 - 3:00 PM', status: 'Completed' },
    // Championship
    { id: 'fn-1', round: 'Championship Final', team1: 'San Antonio Vipers', team2: 'Kapitolyo Knights', score1: 86, score2: 83, winner: 'San Antonio Vipers', court: 'Court 1', time: 'May 22 - 4:00 PM', status: 'Completed' }
  ],
  players: [
    { id: 'p-1', name: 'Jayson Castro Jr.', number: 7, pos: 'Point Guard (PG)', age: 17, verified: true, points: 24, assists: 8, rebounds: 4 },
    { id: 'p-2', name: 'Kobe Paras Ramos', number: 24, pos: 'Shooting Guard (SG)', age: 17, verified: true, points: 19, assists: 3, rebounds: 6 },
    { id: 'p-3', name: 'Carl Tamayo Cruz', number: 15, pos: 'Small Forward (SF)', age: 18, verified: true, points: 16, assists: 2, rebounds: 9 },
    { id: 'p-4', name: 'Kai Sotto Gomez', number: 11, pos: 'Power Forward (PF)', age: 18, verified: true, points: 14, assists: 1, rebounds: 12 },
    { id: 'p-5', name: 'June Mar Fajardo Jr.', number: 34, pos: 'Center (C)', age: 17, verified: true, points: 13, assists: 0, rebounds: 14 },
    { id: 'p-6', name: 'Gabe Norwood Diaz', number: 5, pos: 'Guard (G)', age: 16, verified: true, points: 8, assists: 5, rebounds: 3 }
  ],
  announcements: [
    {
      id: 'ann-1',
      title: 'Championship Finals Schedule Confirmed',
      content: 'Championship game between Vipers and Knights will commence at Court 1 on Saturday, 4:00 PM. Gate opens at 2:30 PM.',
      date: '2 hours ago',
      author: 'Tournament Committee',
      category: 'Schedule'
    },
    {
      id: 'ann-2',
      title: 'Roster Verification & Medical Clearances Notice',
      content: 'All coaches must present original PSA birth certificates and signed guardian consent waivers before match tip-off.',
      date: 'Yesterday',
      author: 'Eligibility Committee',
      category: 'Eligibility'
    },
    {
      id: 'ann-3',
      title: 'Court 2 Lighting Maintenance Complete',
      content: 'Lighting fixtures at Court 2 have been calibrated and approved for evening slot matches.',
      date: '3 days ago',
      author: 'Venue Operations',
      category: 'Venue'
    }
  ]
};

// Initialize Store
function getStore() {
  const data = localStorage.getItem('hoopflow_db');
  if (!data) {
    localStorage.setItem('hoopflow_db', JSON.stringify(DEFAULT_STORE));
    return DEFAULT_STORE;
  }
  try {
    return JSON.parse(data);
  } catch (e) {
    return DEFAULT_STORE;
  }
}

function saveStore(data) {
  localStorage.setItem('hoopflow_db', JSON.stringify(data));
}

// Global accessor. Merge rather than replace: app.js contributes toast + theme
// helpers to the same namespace, and load order must not decide what survives.
window.HF = Object.assign(window.HF || {}, {
  get: getStore,
  save: saveStore,
  getRole: () => {
    return localStorage.getItem('hoopflow_role') || 'organizer';
  },
  setRole: (role) => {
    localStorage.setItem('hoopflow_role', role);
    window.location.reload();
  }
});

