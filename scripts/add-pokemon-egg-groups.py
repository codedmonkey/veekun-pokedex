import pandas as pd
import requests
from bs4 import BeautifulSoup


# Map Bulbapedia Egg Group Names to pokedex ids
egg_groups = {'Monster': 1, 'Water 1': 2, 'Bug': 3, 'Flying': 4, 'Field': 5, 'Fairy': 6, 'Grass': 7, 'Human-Like': 8,
              'Water 3': 9, 'Mineral': 10, 'Amorphous': 11, 'Water 2': 12, 'Ditto': 13, 'Dragon': 14,
              'No Eggs Discovered': 15}

# Scrape data
url = 'https://bulbapedia.bulbagarden.net/wiki/List_of_Pok%C3%A9mon_by_Egg_Group'
response = requests.get(url).text
soup = BeautifulSoup(response, 'html.parser')
pokemon_egg_groups = soup.find('table', class_='sortable roundy')
df = pd.read_html(str(pokemon_egg_groups))[0]
df = df[['#', 'Pokémon', 'Egg Group 1', 'Egg Group 2']]
species_id = []
egg_group_id = []


for index, row in df.iterrows():
    # Check egg group is a string
    # if it is, append the species id and egg group id to temp list
    if isinstance(df.at[index, 'Egg Group 1'], str):
        species_id.append(int(df.at[index, '#']))
        egg_group_id.append(int(egg_groups[df.at[index, 'Egg Group 1'].replace('*', '')]))
    if isinstance(df.at[index, 'Egg Group 2'], str):
        species_id.append(int(df.at[index, '#']))
        egg_group_id.append(int(egg_groups[df.at[index, 'Egg Group 2'].replace('*', '')]))

#writes data to csv
pd.DataFrame({'species_id': species_id, 'egg_group_id': egg_group_id}).to_csv('../pokedex/data/csv/pokemon_egg_groups.csv', index=False)