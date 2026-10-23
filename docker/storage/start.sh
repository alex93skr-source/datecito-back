#!/bin/sh
# Arranca SeaweedFS con API S3 usando las credenciales del .env.
set -e
cat > /tmp/s3.json <<EOF
{
  "identities": [
    {
      "name": "datecito",
      "credentials": [{ "accessKey": "${S3_KEY}", "secretKey": "${S3_SECRET}" }],
      "actions": ["Admin", "Read", "Write", "List", "Tagging"]
    }
  ]
}
EOF
exec weed server -dir=/data -s3 -s3.port=8333 -s3.config=/tmp/s3.json -master.volumeSizeLimitMB=1024 -volume.max=0
