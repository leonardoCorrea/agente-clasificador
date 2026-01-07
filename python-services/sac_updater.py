import os
import requests
from bs4 import BeautifulSoup
import pandas as pd
import mysql.connector
from datetime import datetime
import sys
import re

# Configuración de Base de Datos
DB_CONFIG = {
    'host': '192.168.100.103',
    'user': 'admin',
    'password': '123456',
    'database': 'facturacion_aduanera'
}

# URL Oficial de Documentos de Interés (Hacienda CR)
BASE_URL = "https://www.hacienda.go.cr"
DOCS_URL = "https://www.hacienda.go.cr/DocumentosInteres.html"

def get_db_connection():
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except mysql.connector.Error as err:
        print(f"Error connecting to DB: {err}")
        sys.exit(1)

def find_latest_arancel_url():
    print(f"Buscando archivo de Arancel en {DOCS_URL}...")
    try:
        headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'}
        response = requests.get(DOCS_URL, headers=headers, verify=False) # Skip verify for legacy gov sites
        response.raise_for_status()
        
        soup = BeautifulSoup(response.content, 'html.parser')
        
        # Buscar enlaces que contengan "Arancel" y terminen en xlsx o xls
        links = soup.find_all('a', href=True)
        arancel_link = None
        
        for link in links:
            href = link['href']
            text = link.get_text().lower()
            if 'arancel' in text and ('xlsx' in href or 'xls' in href):
                # Prioritize "Arancel Actualizado" or typical naming
                print(f"Encontrado posible archivo: {text} -> {href}")
                arancel_link = href
                if 'actualizado' in text:
                    break # Best match
        
        if arancel_link:
            if not arancel_link.startswith('http'):
                arancel_link = BASE_URL + '/' + arancel_link.lstrip('/')
            return arancel_link
        
        return None

    except Exception as e:
        print(f"Error searching for URL: {e}")
        return None

def download_file(url, local_filename):
    print(f"Descargando: {url}")
    try:
        headers = {'User-Agent': 'Mozilla/5.0'}
        with requests.get(url, stream=True, headers=headers, verify=False) as r:
            r.raise_for_status()
            with open(local_filename, 'wb') as f:
                for chunk in r.iter_content(chunk_size=8192):
                    f.write(chunk)
        print(f"Archivo guardado en: {local_filename}")
        return True
    except Exception as e:
        print(f"Error descargando archivo: {e}")
        return False

def parse_and_insert(file_path):
    print("Procesando archivo Excel (esto puede tomar unos minutos)...")
    
    conn = get_db_connection()
    cursor = conn.cursor()
    
    try:
        # Detectar cabeceras. Usualmente fila 0 o 1.
        # Asumimos columnas comunes: 'Partida', 'Descripción', 'DAI', 'SC', 'Ley', 'Ventas'
        df = pd.read_excel(file_path)
        
        # Normalizar nombres de columnas
        df.columns = df.columns.str.strip().str.lower()
        print("Columnas encontradas:", df.columns.tolist())
        
        # Mapeo flexible de columnas
        col_map = {
            'codigo': ['partida', 'código', 'codigo sac', 'inciso'],
            'desc': ['descripción', 'descripcion', 'mercancía'],
            'dai': ['dai'],
            'sc': ['sc', 'selectivo'],
            'ley': ['ley', '6946', 'ley 6946'],
            'iva': ['iva', 'ventas', 'impuesto ventas']
        }
        
        def find_col(keys):
            for k in keys:
                for col in df.columns:
                    if k in col:
                        return col
            return None

        c_codigo = find_col(col_map['codigo'])
        c_desc = find_col(col_map['desc'])
        c_dai = find_col(col_map['dai'])
        c_sc = find_col(col_map['sc'])
        c_ley = find_col(col_map['ley'])
        c_iva = find_col(col_map['iva'])
        
        if not (c_codigo and c_desc):
            print("Error: No se encontraron las columnas críticas (Partida/Descripción).")
            return

        print(f"Mapeo de columnas: Partida='{c_codigo}', Desc='{c_desc}', DAI='{c_dai}', SC='{c_sc}'")

        success_count = 0
        
        sql = """INSERT INTO catalogo_arancelario 
                 (codigo_sac, descripcion, dai, sc, ley_6946, iva) 
                 VALUES (%s, %s, %s, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE 
                 descripcion = VALUES(descripcion), dai = VALUES(dai), 
                 sc = VALUES(sc), ley_6946 = VALUES(ley_6946), iva = VALUES(iva)"""

        for index, row in df.iterrows():
            try:
                code = str(row[c_codigo]).strip()
                desc = str(row[c_desc]).strip()
                
                # Limpiar código
                code = ''.join(c for c in code if c.isdigit() or c == '.')
                
                if len(code) < 4: continue # Skip junk rows
                
                # Obtener valores numéricos
                def get_val(col):
                    if not col: return 0
                    val = row[col]
                    if pd.isna(val) or val == 'EX' or val == 'LC': return 0 # Handle exceptions later
                    try:
                        return float(val)
                    except:
                        return 0

                val_dai = get_val(c_dai)
                val_sc = get_val(c_sc)
                val_ley = get_val(c_ley)
                val_iva = get_val(c_iva)
                
                cursor.execute(sql, (code, desc, val_dai, val_sc, val_ley, val_iva))
                success_count += 1
                
                if success_count % 1000 == 0:
                    print(f"Procesados {success_count} registros...")
                    conn.commit()
                    
            except Exception as row_err:
                continue

        conn.commit()
        print(f"Total importados/actualizados: {success_count} registros.")

    except Exception as e:
        print(f"Error procesando Excel: {e}")
    finally:
        cursor.close()
        conn.close()

if __name__ == "__main__":
    # 1. Verificar si existe archivo local manual
    manual_file = "ArancelActualizado.xls"
    if os.path.exists(manual_file):
        print(f"Archivo local encontrado: {manual_file}. Omitiendo descarga.")
        parse_and_insert(manual_file)
    else:
        # 2. Si no, intentar descarga
        print("No se encontró archivo local. Intentando descargar...")
        download_url = find_latest_arancel_url()
        
        # Fallback URL
        if not download_url:
            download_url = "https://www.hacienda.go.cr/docs/ArancelActualizado.xlsx" 

        if download_url:
            filename = "Arancel_CR_Latest.xlsx"
            if download_file(download_url, filename):
                parse_and_insert(filename)
            else:
                print("Falló la descarga y no hay archivo local.")
        else:
            print("No se pudo obtener una URL de descarga.")
