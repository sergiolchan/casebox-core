Ext.namespace('CB');

Ext.define('CB.widget.block.Chart', {
    extend: 'CB.widget.block.Base'

    ,alias: 'CBWidgetBlockChart'

    ,xtype: 'CBWidgetBlockChart'

    ,width: 300
    ,height: 300
    ,minWidth: 200
    ,minHeight: 200

    ,initComponent: function(){

        Ext.apply(this, {
            border: false
            ,autoHeight: true

            ,layout: {
                type: 'vbox'
                ,pack: 'top'
            }

            ,listeners: {
                scope: this
                ,afterrender: this.onAfterRender
            }
        });

        this.callParent(arguments);

        this.initSeriesStyles();

        this.initChartConfigs();
    }

    ,initSeriesStyles: function() {
        this.seriesStyles = [];
        for (var i = 0; i < App.colors.length; i++) {
            this.seriesStyles.push({
                color: App.colors[i]
            });
        }
    }

    ,initChartConfigs: function() {
        this.chartDataStore = new Ext.data.JsonStore({
            autoDestroy: false
            ,model: 'GenericCount'
        });

        var tipsCfg = {
            trackMouse: true
            ,renderer: function(tooltip, record, item) {
                tooltip.setHtml(record.get('name') + ': ' + record.get('count'));
            }
        };

        this.chartConfigs = {
            'bar': {
                xtype: 'cartesian'
                ,reference: 'chart'
                ,width: '100%'
                ,store: this.chartDataStore
                ,colors: App.colors
                ,flipXY: true
                ,axes: [
                    {
                        type: 'numeric'
                        ,position: 'bottom'
                        ,fields: 'count'
                        ,grid: true
                    },{
                        type: 'category'
                        ,position: 'left'
                        ,fields: 'shortname'
                        ,grid: true
                    }
                ]
                ,series: [{
                    type: 'bar'
                    ,axis: 'left'
                    ,xField: 'shortname'
                    ,yField: 'count'
                    ,style: {
                        opacity: 0.80
                        ,minGapWidth: 10
                    }
                    ,highlight: {
                        fillStyle: 'rgba(249, 204, 157, 1.0)'
                        ,strokeStyle: 'black'
                        ,radius: 10
                    }
                    ,label: {
                        field: 'count'
                        ,display: 'insideEnd'
                    }
                    ,title: 'count'
                    ,tips: tipsCfg
                    ,listeners: {
                        scope: this
                        ,itemclick: this.onChartItemClick
                    }
                }]
            }
            ,'column': {
                xtype: 'chart'
                ,reference: 'chart'
                ,width: '100%'
                ,store: this.chartDataStore
                ,colors: App.colors
                ,axes: [{
                        type: 'numeric'
                        ,position: 'left'
                        ,adjustByMajorUnit: true
                        ,fields: ['count']
                        ,grid: true
                    }, {
                        type: 'category'
                        ,position: 'bottom'
                        ,fields: ['shortname']
                        ,grid: true
                        ,label: {
                            rotate: {degrees: -90}
                        }
                    }
                ]
                ,series: [{
                    type: 'bar'
                    ,xField: 'shortname'
                    ,yField: ['count']
                    ,stacked: true
                    ,highlight: {
                        fillStyle: 'yellow'
                    }
                    ,label: {
                        field: 'count'
                        ,display: 'insideEnd'
                    }
                    ,title: 'count'
                    ,tips: tipsCfg
                    ,listeners: {
                        scope: this
                        ,itemclick: this.onChartItemClick
                    }
                }]
            }
            ,'pie': {
                xtype: 'polar'
                ,reference: 'chart'
                ,theme: 'default-gradients'
                ,width: '100%'
                ,store: this.chartDataStore
                ,series: [{
                    type: 'pie',
                    // donut: 0,
                    angleField: 'count',
                    label: {
                        field: 'shortname',
                        display: 'outside',
                        calloutLine: {
                            length: 60,
                            width: 3
                        }
                    },
                    showInLegend: true
                    ,highlight: true
                    ,highlightCfg: {
                        'stroke-width': 1,
                        stroke: '#fff'
                    }
                    ,tips: tipsCfg

                    ,listeners: {
                        scope: this
                        ,itemclick: this.onChartItemClick
                    }
                }]
            }
        };

        return this.chartConfigs;
    }

    ,onAfterRender: function(p) {
        if(!Ext.isEmpty(this.config.data)) {
            this.loadData(this.config.data);
        }
    }

    ,loadData: function(data, overrides) {
        var rez = {
            data: {}
            ,charts: []
        }
        ,d = rez.data;

        Ext.apply(rez, overrides);

        if(data.view) {
            var vp = data.view;

            if(Ext.isEmpty(rez.charts)  && vp.chartType) {
                rez.charts = Ext.isString(vp.chartType)
                    ? [vp.chartType]
                    : vp.chartType;
            }

            if(Ext.isEmpty(rez.facet) && vp.rows && !Ext.isEmpty(vp.rows.facet)) {
                rez.facet = vp.rows.facet;
            }
        }

        Ext.iterate(
            data.facets
            ,function(key, val, o) {
                d[key] = CB.facet.List.prototype.getFacetData(key, val.items);

                //set first facet as selected if not specified
                if(Ext.isEmpty(rez.facet)) {
                    rez['facet'] = key;
                }

                for (var i = 0; i < d[key].length; i++) {
                    if(Ext.isObject(d[key][i].items)) {
                        d[key][i].name = d[key][i].items.name;
                        d[key][i].count = d[key][i].items.count;
                    } else {
                        d[key][i].count = d[key][i].items;
                    }

                    d[key][i].shortname = htmlEntityDecode(App.shortenString(d[key][i].name, 30));
                }

                if(data.sorter) {
                    d[key] = Ext.Array.sort(d[key], data.sorter);
                }
            }
            ,this
        );

        if(d[rez.facet]) {
            this.chartDataStore.loadData(Ext.clone(d[rez.facet]));
        } else {
            this.chartDataStore.removeAll();
        }

        if (Ext.isEmpty(rez.charts)) {
            rez.charts = ['pie'];
        }

        this.chartData = rez;

        this.changeCharts(rez.charts);

        return rez;
    }

    ,changeCharts: function(charts) {
        this.removeAll(true);

        var cfg = Ext.clone(this.chartConfigs[charts[0]]);

        if(!Ext.isEmpty(cfg)) {
            // cfg.height = Math.max(cfg.store.getCount() * 25, 300);
            cfg.height = this.body.getHeight() - 20;

            if (charts[0] === 'pie') {
                cfg.insetPadding = 75;
            } else {
                cfg.insetPadding = 35;
            }

            cfg.legend = (this.showLegend !== false)
                ? {
                    docked: 'right'
                    // ,boxStrokeWidth: 0
                }
                : false;

            this.chart = Ext.create(cfg);

            this.add(this.chart);
        }

        this.updateLayout();
    }

    ,onChartItemClick: function(o, e) {
        this.fireEvent('itemclick', o, e);
    }

    ,setLegendVisible: function(visible) {
        this.showLegend = visible;
    }

});
