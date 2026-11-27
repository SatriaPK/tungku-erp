import unittest
from fastapi.testclient import TestClient
from app import app

client = TestClient(app)


def package(id, **changes):
    return dict(id=id, name=f"Studio {id}", track_count=2, recording_hours=4,
                stages=["recording", "mixing"], price=1_500_000) | changes


def payload(catalog, **changes):
    return {"catalog": catalog, "needs": dict(track_count=2, recording_hours=4,
            stages=["recording", "mixing"], budget=2_000_000) | changes}


class RecommendationTests(unittest.TestCase):
    def test_health(self):
        self.assertEqual(client.get('/health').json()['runtime'], 'python')

    def test_fits_current_catalog_and_filters_before_ranking(self):
        rows = [package(1), package(2, track_count=5, recording_hours=10),
                package(3, price=2_500_000), package(4, track_count=1),
                package(5, stages=['recording']), package(6, recording_hours=1),
                package(7, name='DEMO Package')]
        result = client.post('/v1/recommend', json=payload(rows)).json()
        self.assertEqual([r['package_id'] for r in result['data']], [1, 2, 3])
        self.assertFalse(result['data'][2]['within_budget'])
        self.assertEqual(result['meta']['method'], 'standardized_nearest_neighbors')
        self.assertFalse(result['meta']['quality_evaluated'])
        self.assertEqual(result['meta']['excluded_active_packages'], 1)

    def test_exact_fit_and_constant_dimensions(self):
        result = client.post('/v1/recommend', json=payload([package(2), package(1)], budget=1_500_000)).json()
        self.assertEqual(result['data'][0], {'package_id': 1, 'score': 100, 'within_budget': True})

    def test_no_budget_ignores_price_dimension(self):
        result = client.post('/v1/recommend', json=payload([package(1), package(2, price=100)], budget=None)).json()
        self.assertTrue(all(r['score'] == 100 and r['within_budget'] for r in result['data']))
        self.assertEqual(result['data'][0]['package_id'], 2)

    def test_single_package_uses_honest_python_fallback(self):
        result = client.post('/v1/recommend', json=payload([package(1)])).json()
        self.assertEqual(result['data'][0]['score'], 100)
        self.assertEqual(result['meta']['method'], 'content_based_fallback')
        self.assertIsNone(result['meta']['trained_at'])

    def test_empty_catalog_and_train_insufficient_data(self):
        self.assertEqual(client.post('/v1/recommend', json=payload([])).json()['data'], [])
        self.assertEqual(client.post('/v1/train', json={'catalog': [package(1)]}).status_code, 422)

    def test_train_contains_no_fake_metrics(self):
        response = client.post('/v1/train', json={'catalog': [package(1), package(2)]})
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json()['report']['generated_rows'], 0)
        self.assertEqual(response.json()['report']['personal_data_fields'], 0)

    def test_invalid_payloads(self):
        for changes in [dict(track_count=0), dict(stages=['mixing', 'mixing']),
                        dict(stages=['invalid']), dict(recording_hours=0), dict(budget=-1)]:
            self.assertEqual(client.post('/v1/recommend', json=payload([], **changes)).status_code, 422)
        self.assertEqual(client.post('/v1/recommend', json=payload([package(1), package(1)])).status_code, 422)

    def test_post_production_without_recording(self):
        result = client.post('/v1/recommend', json=payload([package(1, stages=['mixing'], recording_hours=0)],
                             stages=['mixing'], recording_hours=0, budget=None)).json()
        self.assertEqual(result['data'][0]['score'], 100)

    def test_catalog_changes_affect_result_immediately(self):
        rows = [package(1), package(2)]
        first = client.post('/v1/recommend', json=payload(rows, budget=1_500_000)).json()
        rows[0]['price'] = 3_000_000
        second = client.post('/v1/recommend', json=payload(rows, budget=1_500_000)).json()
        self.assertEqual(first['data'][0]['package_id'], 1)
        self.assertEqual(second['data'][0]['package_id'], 2)


if __name__ == '__main__':
    unittest.main()
