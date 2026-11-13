# Package recommendation model

The model uses actual records read from this installation's `packages` table. It does not create users, transactions, labels, or training rows. Active package names containing whole words DEMO, DUMMY, TEST, TESTING, or CONTOH are excluded, along with invalid capacities/prices and packages without services. These name checks are conservative heuristics: an unmarked database record is not proof of a real production transaction. Confirm catalog provenance with the studio before describing this as a study of real customers.

## Fit and use

Run from backend:

    php artisan ml:train-package-recommendations

Run independently in each backend deployment. The command fits per-feature population means and standard deviations, then saves catalog vectors and metadata atomically to `storage/app/private/ml/package-neighbors.json`. Fewer than two eligible packages causes an explicit failure without overwriting an existing artifact. No personal data is exported or sent to an external service. Keep the artifact private and out of Git.

Features: track count, recording hours, four service flags, and log(1 + effective package price). Zero-variance features use scale 1. Retrieval uses Euclidean distance in the standardized space, expressed as similarity `100 / (1 + root-mean-square distance)`. Without a budget, the price dimension is omitted. This is unsupervised, instance-based nearest-neighbor retrieval, not a classifier trained on customer choices.

The existing client endpoint `/api/v1/client/package-recommendations` loads the fitted artifact. Candidate packages must still cover all requested services and minimum track/hour capacity. Packages within budget rank first, then similarity, price, and ID. At most three results are returned. An input exactly equal to an eligible package has similarity 100; this does not imply 100% customer satisfaction or prediction accuracy.

The artifact fingerprint binds feature vectors and package IDs to the current eligible catalog. Missing, corrupt, incompatible, or stale artifacts cause an explicitly named `content_based_fallback` response. Retrain after changes to the catalog. UI explanations and metadata must not claim the model was used when fallback is active.

## Current local data audit

At implementation: 14 active packages, 11 labeled DEMO excluded, 3 eligible database catalog packages, 0 generated rows. Both local worktrees use the same database. The artifact report records counts and potential recording-configuration issues for the current fit. This is a very small catalog and ranking resolution is limited. The local database also contains testing activity; project/payment counts are not verified evidence of real customer behavior and are not training labels.

There are no verified held-out client choices. Accuracy, precision@k, recall@k, and conversion uplift are unavailable; no invented evaluation is reported. Feature/API regression tests verify program behavior, not model quality, and their fixtures are never mixed into the local training artifact. To evaluate usefulness later, collect consented recommendation impressions and independently observed choices/outcomes, establish a temporal holdout, and compare against the existing matching baseline.

Algorithm references: https://scikit-learn.org/stable/modules/neighbors.html and https://scikit-learn.org/stable/modules/generated/sklearn.preprocessing.StandardScaler.html . This implementation is native PHP; it does not require scikit-learn or a Python production process.
