# storj-deployment
Full compose multinode deployment for storj-node with exporters and grafana.

This implementation aims to gather all available information in single place and make deployment as simple as possible.

## Deployment example
Grafana storj

![UI](https://user-images.githubusercontent.com/23486452/206063260-b472fdf0-bc39-4ed4-a1b2-dafa934a8733.png)

Grafana host

![UI](https://user-images.githubusercontent.com/23486452/206063367-0df86143-a420-45a5-946f-c77544d51c57.png)

List of web interfaces

![UI List](https://user-images.githubusercontent.com/23486452/205275296-3524e3db-f525-4d6b-818e-08e2646a51fe.png)

storj-node stack

![storj-node stack](https://user-images.githubusercontent.com/23486452/205275465-39f29074-6b33-412d-b4e0-8c9678e09f12.png)

storj-ui stack

![storj-ui stack](https://user-images.githubusercontent.com/23486452/205275530-d2e18160-2909-4f4f-9eb7-ea859280a95a.png)

Portainer

![Docker](https://user-images.githubusercontent.com/23486452/205275612-7faa28e9-57e2-4b1e-a0b9-7d2a2d3fc65a.png)



## Per node compose deployment
* storj-node with log to file
* preconfigured zksync
* logrotate for storj-node
* storj exporter
* storj log exporter
* storj updater
* running on shared dedicated internal network

## Other
* docker host OS monitoring
* prometheus + grafana configuration
* grafana storj and host os monitoring graphs
* simple webside for host displaying all available storj web interfaces
* storj multinode optional deployment

## Credits
Implementation includes following:
* https://github.com/storj/storj
* https://github.com/anclrii/Storj-Exporter
* https://github.com/anclrii/Storj-Exporter-dashboard
* https://github.com/kevinkk525/storj-log-exporter
* https://github.com/prometheus/node_exporter
* https://github.com/rfmoz/grafana-dashboards


# Prepare for deployment

## Build storj-log-exporter docker image
```
cd ~
git clone https://github.com/kevinkk525/storj-log-exporter
cd storj-log-exporter
sudo docker build -t storj-log-exporter .
```

# Node deployment
Example for node "01". Increment number as needed.

## Prepare storj node implementation
Get your auth token and Generate node identity as normal with name nodeXX, replace XX with node number. Example: node01
https://www.storj.io/host-a-node

Linux example for node01:
```
cd ~
wget https://github.com/storj/storj/releases/latest/download/identity_linux_amd64.zip
unzip -o identity_linux_amd64.zip
chmod +x identity
./identity create node01
./identity authorize node01 <email:characterstring>
grep -c BEGIN ~/.local/share/storj/identity/node01/ca.cert
grep -c BEGIN ~/.local/share/storj/identity/node01/identity.cert
```
*grep should result in 2 and 3 respectively.*

Port forward 140XX, replace XX with node number. Example: 14001

## Initialize storj node
```
docker run --rm -e SETUP="true" \
    --user $(id -u):$(id -g) \
    --mount type=bind,source="/root/.local/share/storj/identity/node01",destination=/app/identity \
    --mount type=bind,source="/mnt/node01",destination=/app/config \
    --name storj-node01 storjlabs/storagenode:latest
```

## Deploy storj node
Checkout storj-node stack for new node
```
cd ~
rm /mnt/node01/config.yaml
svn checkout https://github.com/tomaae/storj-deployment.git/trunk/storj-nodeXX storj-node01
```

Edit stack configuration:
```
sed -i 's/XX/01/g' ./storj-node01/.env
```

Edit storj-node01/.env attributes as needed:
```
NODE_ID=<2 number node id>
NODE_EMAIL=<contact email>
NODE_EXTERNAL=<external fqdn>:150XX
NODE_STORAGE=<storj node size>
NODE_WALLET=<wallet address>
NODE_MNT=<storj node config mount point>
NODE_IDENTITY=<storj node iudentity directory>
```
*node ID, ports and NODE_IDENTITY should be preconfigured correctly*

Deploy node
```
cd storj-node01
docker compose up -d
```

# Grafana deployment
Checkout storj-ui stack
```
cd ~
svn checkout https://github.com/tomaae/storj-deployment.git/trunk/storj-ui
```

Edit stack configuration storj-ui/.env
```
PROMETHEUS_MOUNT=<ui mount pointmount point>/prometheus
GRAFANA_MOUNT=<ui mount point>/grafana

GF_SECURITY_ADMIN_PASSWORD=<password>
GF_USERS_ALLOW_SIGN_UP=false
GF_INSTALL_PLUGINS=yesoreyeram-boomtable-panel

DOCKER_HOST_HOSTNAME=<docker host fqdn>
```

Edit storj-ui/prometheus.yml, append block for each node, replace XX with node ID
```
  - job_name: storj-nodeXX
    scrape_interval: 60s
    scrape_timeout: 20s
    metrics_path: /
    static_configs:
      - targets: ["storj-exporterXX:9651", "storj-log-exporterXX:9144"]
        labels:
          instance: "nodeXX"
```

Deploy storj-ui
```
cd storj-ui
docker compose up -d
```

Import grafana dashboard:

https://raw.githubusercontent.com/tomaae/storj-deployment/main/storj-ui/dashboard_exporter_combined.json

# (Optional) Deploy docker host statistics
If you want docker host statistics

Checkout storj-host stack
```
cd ~
svn checkout https://github.com/tomaae/storj-deployment.git/trunk/storj-hostXX storj-host01
sed -i 's/XX/01/g' ./storj-host01/.env
cd storj-host01
docker compose up -d
```

Edit storj-ui/prometheus.yml, append block for each host, replace XX with host ID
```
  - job_name: storj-hostXX
    static_configs:
    - targets: ['host-exporterXX:9100']
      labels:
        instance: "hostXX"
```

Deploy updated storj-ui
```
cd storj-ui
docker compose down
docker compose up -d
```

Import grafana dashboard:

https://raw.githubusercontent.com/tomaae/storj-deployment/main/storj-ui/node-exporter-full.json


# (Optional) multinode deployment
If you want to also deploy official multinode

Generate identity for multinode:
```
cd ~
./identity create multinode --difficulty 10
```

Deploy docker image:
```
docker run -d --restart unless-stopped \
    --user $(id -u):$(id -g) \
    -p 10000:15002/tcp \
    --network storj-bridge
    --mount type=bind,source="/root/.local/share/storj/identity/multinode/ca.key",destination=/app/identity \
    --mount type=bind,source="/mnt/multinode",destination=/app/config \
    --name storj-multinode storjlabs/multinode:latest
```

Issue API key:
```
docker exec -it storj-node01 ./storagenode issue-apikey --identity-dir /app/identity --config-dir /app/config --log.output stderr
```
*Change node01 to appropriate node number*

multinode UI will be available on port 10000
When importing new nodes, use internal network addresses instead of public:
```
Public IP Address:
storj-node01:28967
```
