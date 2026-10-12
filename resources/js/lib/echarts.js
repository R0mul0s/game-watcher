/**
 * Registrace částí ECharts — jen to, co graf ceny používá (menší bundle).
 * Vzor z Počasí.
 *
 * @author Roman Hlaváček
 * @created 2026-10-12
 */
import { LineChart } from 'echarts/charts';
import { AxisPointerComponent, GridComponent, LegendComponent, MarkLineComponent, TooltipComponent } from 'echarts/components';
import { use } from 'echarts/core';
import { SVGRenderer } from 'echarts/renderers';

use([LineChart, GridComponent, TooltipComponent, LegendComponent, AxisPointerComponent, MarkLineComponent, SVGRenderer]);
