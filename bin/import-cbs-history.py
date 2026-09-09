#!/usr/bin/env python3
import json
import re
import sqlite3
import os
from bs4 import BeautifulSoup

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RAW_JSON_PATH = os.path.join(BASE_DIR, 'data', 'cbs_league_history_raw.json')
SQLITE_PATH = os.path.join(BASE_DIR, 'data', 'football.sqlite')
SEED_SQL_PATH = os.path.join(BASE_DIR, 'data', 'fantasy_seed.sql')

def escape_sql(s):
    if s is None:
        return 'NULL'
    return "'" + str(s).replace("'", "''") + "'"

print(f"Loading raw CBS history from {RAW_JSON_PATH}...")
with open(RAW_JSON_PATH, 'r') as f:
    raw = json.load(f)

# Connect to SQLite
conn = sqlite3.connect(SQLITE_PATH)
cur = conn.cursor()

# Ensure migration 003 tables exist
with open(os.path.join(BASE_DIR, 'db', 'migrations', '003_fantasy_vault.sql')) as f:
    cur.executescript(f.read())

# Clear existing data for fresh seed
cur.executescript("""
DELETE FROM fantasy_matchups;
DELETE FROM fantasy_standings;
DELETE FROM fantasy_seasons;
DELETE FROM fantasy_franchise_names;
DELETE FROM fantasy_franchises;
""")

sql_statements = [
    "DELETE FROM fantasy_matchups;",
    "DELETE FROM fantasy_standings;",
    "DELETE FROM fantasy_seasons;",
    "DELETE FROM fantasy_franchise_names;",
    "DELETE FROM fantasy_franchises;"
]

# 1. Parse Overview Tab
overview_soup = BeautifulSoup(raw['tabs']['Overview']['html'], 'html.parser')
tables = overview_soup.find_all('table')

t_champs = tables[0]
t_alltime = tables[1]
t_year_champ = tables[2]
t_averages = tables[3]

# Parse Titles
titles_by_name = {
    'Wonder Twins': (4, ['2007', '2013', '2021', '2024'], 3),
    'Single With Children': (4, ['2003', '2004', '2009', '2016'], 11),
    'Injuries R Us': (3, ['2005', '2006', '2019'], 4),
    'Mazies Gang': (3, ['2008', '2010', '2014'], 12),
    'No Sweat': (3, ['2017', '2018', '2022'], 2), # Gee, I'm Spatial
    'Gee, I\'m Spatial': (3, ['2017', '2018', '2022'], 2),
    'BUCBALL': (2, ['2015', '2023'], 10),
    'Schadenfreude': (2, ['2020', '2021'], 7),
    'Drunken Squids': (2, ['2011', '2012'], 9),
    'Viridis Bay Packers': (1, ['2018'], 8),
    'Wicked Noles': (1, ['2022'], 6),
}

# Parse Averages
averages_by_name = {}
for r in t_averages.find_all('tr')[1:]:
    tds = [td.get_text(strip=True) for td in r.find_all(['td', 'th'])]
    if len(tds) >= 3:
        averages_by_name[tds[0]] = (
            float(tds[1]) if tds[1] else None,
            float(tds[2]) if tds[2] else None
        )

# Insert Franchises
franchises = {}
for r in t_alltime.find_all('tr')[1:]:
    tds = [td.get_text(strip=True) for td in r.find_all(['td', 'th'])]
    a = r.find('a', href=re.compile(r'/history/team-overview/\d+'))
    if a:
        m = re.search(r'/history/team-overview/(\d+)', a['href'])
        fid = int(m.group(1))
        team_name = tds[0]
        titles_count = titles_by_name.get(team_name, (0, [], fid))[0]
        avg_finish, avg_pts_yr = averages_by_name.get(team_name, (None, None))
        mgr = tds[7] if len(tds) > 7 else ''
        
        franchises[fid] = {
            'id': fid,
            'name': team_name,
            'managers': mgr,
            'wins': int(tds[1]),
            'losses': int(tds[2]),
            'ties': int(tds[3]),
            'pct': float(tds[4]),
            'pf': float(tds[5]),
            'pa': float(tds[6]),
            'titles': titles_count,
            'avg_finish': avg_finish,
            'avg_pts_year': avg_pts_yr
        }

        cur.execute("""
            INSERT INTO fantasy_franchises 
            (id, current_name, current_managers, wins, losses, ties, win_pct, points_for, points_against, titles_count, avg_finish, avg_pts_year)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (fid, team_name, mgr, franchises[fid]['wins'], franchises[fid]['losses'], franchises[fid]['ties'], franchises[fid]['pct'], franchises[fid]['pf'], franchises[fid]['pa'], titles_count, avg_finish, avg_pts_yr))

        sql_statements.append(
            f"INSERT INTO fantasy_franchises (id, current_name, current_managers, wins, losses, ties, win_pct, points_for, points_against, titles_count, avg_finish, avg_pts_year) "
            f"VALUES ({fid}, {escape_sql(team_name)}, {escape_sql(mgr)}, {franchises[fid]['wins']}, {franchises[fid]['losses']}, {franchises[fid]['ties']}, {franchises[fid]['pct']}, {franchises[fid]['pf']}, {franchises[fid]['pa']}, {titles_count}, {avg_finish or 'NULL'}, {avg_pts_yr or 'NULL'});"
        )

print(f"Inserted {len(franchises)} franchises.")

# 2. Yearly Champions & Runners-Up Mapping
season_champions = {
    2003: (11, 'Married With Kids', None, None, 'Inaugural season'),
    2004: (11, 'Married With Kids', None, None, ''),
    2005: (4, 'Outlaws / Injuries R Us', None, None, ''),
    2006: (4, 'Outlaws / Injuries R Us', None, None, ''),
    2007: (3, 'Wonder Twins', 1, 'Archetypo', '15-week season'),
    2008: (12, 'Mazies Gang', None, None, ''),
    2009: (11, 'Single With Children', None, None, ''),
    2010: (12, 'Mazies Gang', None, None, ''),
    2011: (9, 'Drunken Squids', None, None, ''),
    2012: (9, 'Drunken Squids', None, None, ''),
    2013: (3, 'Wonder Twins', None, None, ''),
    2014: (12, 'Mazies Gang', None, None, ''),
    2015: (10, 'BUCBALL', None, None, ''),
    2016: (11, 'Single With Children', None, None, ''),
    2017: (2, 'No Sweat', None, None, ''),
    2018: (8, 'Viridis Bay Packers', 2, 'No Sweat', 'Co-championship / Split recorded'),
    2019: (4, 'Injuries R Us', None, None, ''),
    2020: (7, 'Schadenfreude', None, None, ''),
    2021: (3, 'Wonder Twins', 7, 'Schadenfreude', 'Co-champions'),
    2022: (6, 'Wicked Noles', 2, 'No Sweat', 'Co-champions (Week 17 Hamlin stoppage)'),
    2023: (10, 'BUCBALL', 1, 'Archetypo', 'Wally runner-up finish (13-5)'),
    2024: (3, 'Wonder Twins', 7, 'Schadenfreude', 'Tamara 4th ring'),
    2025: (None, None, None, None, 'Current season')
}

for yr in range(2003, 2026):
    champ_id, champ_name, run_id, run_name, notes = season_champions.get(yr, (None, None, None, None, ''))
    cur.execute("""
        INSERT INTO fantasy_seasons (year, champion_franchise_id, champion_name, runner_up_franchise_id, runner_up_name, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    """, (yr, champ_id, champ_name, run_id, run_name, notes))

    c_id_sql = str(champ_id) if champ_id is not None else 'NULL'
    r_id_sql = str(run_id) if run_id is not None else 'NULL'
    sql_statements.append(
        f"INSERT INTO fantasy_seasons (year, champion_franchise_id, champion_name, runner_up_franchise_id, runner_up_name, notes) "
        f"VALUES ({yr}, {c_id_sql}, {escape_sql(champ_name)}, {r_id_sql}, {escape_sql(run_name)}, {escape_sql(notes)});"
    )

print("Inserted seasons 2003-2025.")

# 3. Parse Standings & Matchups from Each Season Page
franchise_names_map = {}
matchups_count = 0
standings_count = 0

for year_str in sorted(raw['seasons'].keys()):
    year = int(year_str)
    s_html = raw['seasons'][year_str]['html']
    soup = BeautifulSoup(s_html, 'html.parser')
    tables = soup.find_all('table')

    # Parse Standings Table
    for t in tables:
        t_text = t.get_text()
        if 'PCT' in t_text and ('PF' in t_text or 'Wks' in t_text) and 'Away Team' not in t_text:
            rows = t.find_all('tr')
            current_division = None
            rank = 1
            for r in rows:
                tds = [td.get_text(strip=True) for td in r.find_all(['td', 'th'])]
                if not tds:
                    continue
                if 'Division' in tds[0]:
                    current_division = tds[0]
                    continue
                if tds[0] in ['Team', 'Record', 'W', 'League Records', 'Custom Records']:
                    continue

                # Team row
                a = r.find('a', href=re.compile(r'/history/team-overview/\d+'))
                if a and len(tds) >= 7:
                    m = re.search(r'/history/team-overview/(\d+)', a['href'])
                    if not m:
                        continue
                    fid = int(m.group(1))
                    t_name = a.get_text(strip=True)
                    wins = int(tds[1]) if tds[1].isdigit() else 0
                    losses = int(tds[2]) if tds[2].isdigit() else 0
                    ties = int(tds[3]) if tds[3].isdigit() else 0
                    pct = float(tds[4]) if tds[4].replace('.', '').isdigit() else 0.0
                    pf = float(tds[5]) if tds[5].replace('.', '').isdigit() else 0.0
                    pa = float(tds[6]) if tds[6].replace('.', '').isdigit() else 0.0

                    franchise_names_map.setdefault(fid, {}).setdefault(t_name, []).append(year)

                    cur.execute("""
                        INSERT OR REPLACE INTO fantasy_standings
                        (season_year, franchise_id, team_name, division_name, wins, losses, ties, win_pct, points_for, points_against, rank)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    """, (year, fid, t_name, current_division, wins, losses, ties, pct, pf, pa, rank))

                    sql_statements.append(
                        f"INSERT INTO fantasy_standings (season_year, franchise_id, team_name, division_name, wins, losses, ties, win_pct, points_for, points_against, rank) "
                        f"VALUES ({year}, {fid}, {escape_sql(t_name)}, {escape_sql(current_division)}, {wins}, {losses}, {ties}, {pct}, {pf}, {pa}, {rank}) "
                        f"ON CONFLICT (season_year, franchise_id) DO UPDATE SET team_name = EXCLUDED.team_name, wins = EXCLUDED.wins, losses = EXCLUDED.losses, ties = EXCLUDED.ties, win_pct = EXCLUDED.win_pct, points_for = EXCLUDED.points_for, points_against = EXCLUDED.points_against, rank = EXCLUDED.rank;"
                    )
                    standings_count += 1
                    rank += 1

    # Parse Matchup Tables (2007-2024)
    match_tables = [t for t in tables if 'Away Team' in t.get_text() and 'Home Team' in t.get_text()]
    for default_week, t in enumerate(match_tables, start=1):
        parent_div = t.find_parent('div', class_=lambda c: c and 'period' in str(c).lower())
        week_num = default_week
        if parent_div:
            for cls in parent_div.get('class', []):
                m_p = re.match(r'period(\d+)', cls)
                if m_p:
                    week_num = int(m_p.group(1))

        rows = t.find_all('tr')[1:]
        for r in rows:
            cells = r.find_all('td')
            if len(cells) < 3:
                continue
            
            away_td, home_td, score_td = cells[0], cells[1], cells[2]
            away_a = away_td.find('a', href=re.compile(r'/history/team-overview/\d+'))
            home_a = home_td.find('a', href=re.compile(r'/history/team-overview/\d+'))
            if not away_a or not home_a:
                continue

            away_fid = int(re.search(r'/history/team-overview/(\d+)', away_a['href']).group(1))
            home_fid = int(re.search(r'/history/team-overview/(\d+)', home_a['href']).group(1))
            away_name = away_a.get_text(strip=True)
            home_name = home_a.get_text(strip=True)

            score_text = score_td.get_text(strip=True)
            m_score = re.match(r'([0-9.]+)\s*-\s*([0-9.]+)', score_text)
            if not m_score:
                continue

            away_score = float(m_score.group(1))
            home_score = float(m_score.group(2))
            diff = round(abs(away_score - home_score), 2)
            winner_id = home_fid if home_score > away_score else (away_fid if away_score > home_score else None)
            
            # CBS recap link
            recap_a = score_td.find('a')
            recap_id = None
            if recap_a and recap_a.get('href'):
                m_rec = re.search(r'/content/fantasy_journalist_game_recap/(\d+)', recap_a['href'])
                if m_rec:
                    recap_id = m_rec.group(1)

            # Playoff flag
            is_playoff = 1 if ((year < 2021 and week_num >= 15) or (year >= 2021 and week_num >= 15)) else 0

            cur.execute("""
                INSERT OR REPLACE INTO fantasy_matchups
                (season_year, week_number, away_franchise_id, away_team_name, away_score, home_franchise_id, home_team_name, home_score, winner_franchise_id, point_diff, is_playoff, recap_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (year, week_num, away_fid, away_name, away_score, home_fid, home_name, home_score, winner_id, diff, is_playoff, recap_id))

            w_sql = str(winner_id) if winner_id is not None else 'NULL'
            sql_statements.append(
                f"INSERT INTO fantasy_matchups (season_year, week_number, away_franchise_id, away_team_name, away_score, home_franchise_id, home_team_name, home_score, winner_franchise_id, point_diff, is_playoff, recap_id) "
                f"VALUES ({year}, {week_num}, {away_fid}, {escape_sql(away_name)}, {away_score}, {home_fid}, {escape_sql(home_name)}, {home_score}, {w_sql}, {diff}, {is_playoff}, {escape_sql(recap_id)}) "
                f"ON CONFLICT (season_year, week_number, away_franchise_id, home_franchise_id) DO NOTHING;"
            )
            matchups_count += 1

# 4. Insert Franchise Name History
for fid, name_dict in franchise_names_map.items():
    for name, yrs in name_dict.items():
        min_yr = min(yrs)
        max_yr = max(yrs)
        cur.execute("""
            INSERT OR REPLACE INTO fantasy_franchise_names (franchise_id, name, first_year, last_year)
            VALUES (?, ?, ?, ?)
        """, (fid, name, min_yr, max_yr))
        sql_statements.append(
            f"INSERT INTO fantasy_franchise_names (franchise_id, name, first_year, last_year) "
            f"VALUES ({fid}, {escape_sql(name)}, {min_yr}, {max_yr}) "
            f"ON CONFLICT (franchise_id, name) DO UPDATE SET first_year = EXCLUDED.first_year, last_year = EXCLUDED.last_year;"
        )

conn.commit()

# Save seed SQL
with open(SEED_SQL_PATH, 'w') as f:
    f.write("\n".join(sql_statements) + "\n")

print(f"✅ Ingestion complete!")
print(f" - Franchises: {len(franchises)}")
print(f" - Seasons: 23 (2003-2025)")
print(f" - Standings rows: {standings_count}")
print(f" - Matchups: {matchups_count}")
print(f" - Seed SQL written to: {SEED_SQL_PATH}")

conn.close()
