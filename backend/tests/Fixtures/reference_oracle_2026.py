# Calcul INDÉPENDANT du moteur PHP (rôle du « calcul Excel »), sur le barème fictif Sahel 2026.
# Régénérer : python3 reference_oracle_2026.py reference_profiles_2026.input.csv > reference_profiles_2026.csv
from decimal import Decimal as D, ROUND_HALF_UP
from datetime import date
import csv, sys

BASE = {
 'promenade_affaires': [(1,3,57000),(3,7,75000),(7,11,94000),(11,15,123000),(15,24,151000),(24,None,188000)],
 'transport_propre_compte': [(1,7,99000),(7,11,125000),(11,None,167000)],
 'transport_public_marchandises': [(1,11,157000),(11,None,219000)],
 'transport_public_voyageurs': [(1,7,172000),(7,11,204000),(11,None,250000)],
 'deux_roues': [(1,3,19000),(3,None,31500)],
}
USAGE = {'personnel': D('1.00'), 'professionnel': D('1.15')}
AGE = [(18,21,D('1.45')),(21,25,D('1.25')),(25,65,D('1.00')),(65,75,D('1.10')),(75,None,D('1.30'))]
LIC = [(0,1,D('1.30')),(1,3,D('1.15')),(3,None,D('1.00'))]
ZONE = {'douala':D('1.20'),'yaounde':D('1.10'),'autres_villes':D('1.00'),'zone_rurale':D('0.90')}

def band(bands, v):
    for lo, hi, c in bands:
        if v >= lo and (hi is None or v < hi): return c
    raise ValueError(v)

def years(a, b):
    y = b.year - a.year
    # anniversaire pas encore atteint ; un 29/02 compte le 01/03 les années non bissextiles
    if (b.month, b.day) < (a.month, a.day): y -= 1
    return y

def rnd(x): return int(x.quantize(D('1'), rounding=ROUND_HALF_UP))

rows = list(csv.DictReader(open(sys.argv[1])))
for r in rows:
    eff = date.fromisoformat(r['effective_date'])
    p = D(band(BASE[r['category']], int(r['fiscal_power'])))
    p *= USAGE[r['usage']]
    p *= band(AGE, years(date.fromisoformat(r['birth_date']), eff))
    p *= band(LIC, years(date.fromisoformat(r['license_date']), eff))
    p *= ZONE[r['zone']]
    p *= D(r['bonus_malus'])
    net = rnd(p)
    fees = 6000
    tca = rnd((net + fees) * D('0.145'))
    fga = rnd(net * D('0.025'))
    timbre = 1000
    r['expected_net_premium'] = net
    r['expected_total'] = net + fees + tca + fga + timbre
w = csv.DictWriter(sys.stdout, fieldnames=rows[0].keys(), lineterminator='\n')
w.writeheader(); w.writerows(rows)
