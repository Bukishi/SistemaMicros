from flask import Flask, render_template
from db import get_connection

app = Flask(__name__)

@app.route('/')
def mapa():
    conn = get_connection()
    cur = conn.cursor()

    rutas_data = []
    cur.execute("SELECT id, nombre, color FROM ruta")
    rutas = cur.fetchall()

    for ruta in rutas:
        ruta_id, nombre, color = ruta

        # Coordenadas ordenadas
        cur.execute("""
            SELECT lat, lng FROM coordenada
            WHERE ruta_id = %s
            ORDER BY orden
        """, (ruta_id,))
        coordenadas = [{'lat': lat, 'lng': lng} for lat, lng in cur.fetchall()]

        # Paraderos
        cur.execute("""
            SELECT nombre, lat, lng FROM paradero
            WHERE ruta_id = %s
        """, (ruta_id,))
        paraderos = [{'nombre': n, 'lat': lat, 'lng': lng} for n, lat, lng in cur.fetchall()]

        rutas_data.append({
            'nombre': nombre,
            'color': color,
            'coordenadas': coordenadas,
            'paraderos': paraderos
        })

    cur.close()
    conn.close()

    return render_template('mapa.html', rutas=rutas_data)

if __name__ == '__main__':
    app.run(debug=True)
