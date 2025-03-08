from flask import Flask, request, jsonify

app = Flask(__name__)

@app.route('/run_rl', methods=['POST'])
def run_rl():
    # Get JSON data from the request
    received_data = request.get_json()

    # Validate JSON data
    if not received_data or 'output' not in received_data:
        return jsonify({"status": "error", "message": "Invalid or missing JSON data"}), 400

    # Extract data
    revision_data = received_data['output']

    # Dictionary to store format stats
    format_scores = {}

    # Process the received revision data
    for entry in revision_data:
        fmt = entry.get('format')  # Get format type (e.g., 1, 2, 3, 4)
        score = entry.get('score', 0)  # Get the user's score for this format (default 0)

        if fmt is not None:
            if fmt not in format_scores:
                format_scores[fmt] = {'total_score': 0, 'count': 0}

            # Update stats for this format
            format_scores[fmt]['total_score'] += score
            format_scores[fmt]['count'] += 1

    # Compute average scores for each format
    format_details = []
    best_format = None
    highest_avg_score = -1

    for fmt, data in format_scores.items():
        avg_score = data['total_score'] / data['count'] if data['count'] > 0 else 0

        # Store details in list
        format_details.append({
            "format": fmt,
            "count": data['count'],
            "estimated_value": avg_score,  # Can be changed if using RL algorithms
            "average_score": round(avg_score, 2)
        })

        # Determine the best format
        if avg_score > highest_avg_score:
            highest_avg_score = avg_score
            best_format = fmt

    # Construct response JSON
    response = {
        "status": "success",
        "received_data": received_data,
        "format_details": format_details,
        "chosen_format": best_format,
        "chosen_format_reward": round(highest_avg_score, 2) if best_format is not None else 0
    }

    return jsonify(response)

if __name__ == '__main__':
    app.run(debug=True)
