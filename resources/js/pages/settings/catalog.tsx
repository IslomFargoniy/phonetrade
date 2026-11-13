import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { Chip } from '@/components/chip';
import { Field } from '@/components/field';
import InputError from '@/components/input-error';
import { Panel } from '@/components/panel';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { index } from '@/routes/catalog';
import {
    store as storeAttribute,
    update as updateAttribute,
} from '@/routes/catalog/attributes';
import { store as storeCategory } from '@/routes/catalog/categories';
import { store as storeProduct } from '@/routes/catalog/products';
import { store as storeSupplier } from '@/routes/catalog/suppliers';

type Category = {
    id: number;
    name: string;
    tracking: 'serial' | 'quantity';
    tracking_label: string;
    products_count: number;
};

type Attribute = {
    id: number;
    name: string;
    type: 'select' | 'number' | 'text';
    type_label: string;
    options: string[];
    category_ids: number[];
};

type Product = {
    id: number;
    name: string;
    category_id: number;
    category: string;
    default_warranty_months: number;
    default_max_discount_pct: string;
    is_active: boolean;
};

type Supplier = {
    id: number;
    name: string;
    phone: string | null;
    note: string | null;
};

type Props = {
    categories: Category[];
    attributes: Attribute[];
    products: Product[];
    suppliers: Supplier[];
};

function CategoryChecks({
    categories,
    selected,
    idPrefix,
    error,
}: {
    categories: Category[];
    selected: number[];
    idPrefix: string;
    error?: string;
}) {
    return (
        <fieldset className="flex flex-col gap-1">
            <legend className="mb-1 text-xs font-semibold text-muted-foreground">
                {t('catalog.attribute.categories')}
            </legend>
            <div className="flex flex-wrap gap-x-4">
                {categories.map((category) => (
                    <label
                        key={category.id}
                        htmlFor={`${idPrefix}-cat-${category.id}`}
                        className="flex min-h-11 items-center gap-2 text-sm"
                    >
                        <input
                            id={`${idPrefix}-cat-${category.id}`}
                            type="checkbox"
                            name="category_ids[]"
                            value={category.id}
                            defaultChecked={selected.includes(category.id)}
                            className="size-4 accent-primary"
                        />
                        {category.name}
                    </label>
                ))}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}

function AttributeFields({
    categories,
    attribute,
    errors,
}: {
    categories: Category[];
    attribute?: Attribute;
    errors: Record<string, string>;
}) {
    const prefix = attribute ? `attr-${attribute.id}` : 'attr-new';

    return (
        <>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <Field
                    label={t('catalog.attribute.name')}
                    name="name"
                    id={`${prefix}-name`}
                    required
                    defaultValue={attribute?.name}
                    placeholder="Rang"
                    error={errors.name}
                />
                <SelectField
                    label={t('catalog.attribute.type')}
                    name="type"
                    id={`${prefix}-type`}
                    defaultValue={attribute?.type ?? 'select'}
                    error={errors.type}
                >
                    <option value="select">{t('catalog.type.select')}</option>
                    <option value="number">{t('catalog.type.number')}</option>
                    <option value="text">{t('catalog.type.text')}</option>
                </SelectField>
            </div>
            <Field
                label={t('catalog.attribute.options')}
                name="options"
                id={`${prefix}-options`}
                defaultValue={attribute?.options.join(', ')}
                placeholder="Qora, Oq, Ko'k"
                hint={t('catalog.attribute.options.hint')}
                error={errors.options}
            />
            <CategoryChecks
                categories={categories}
                selected={attribute?.category_ids ?? []}
                idPrefix={prefix}
                error={errors.category_ids}
            />
        </>
    );
}

function AttributeRow({
    attribute,
    categories,
}: {
    attribute: Attribute;
    categories: Category[];
}) {
    const [editing, setEditing] = useState(false);

    if (!editing) {
        return (
            <div className="flex flex-wrap items-center justify-between gap-3 glass-inner px-3.5 py-3">
                <div className="flex min-w-0 flex-col gap-1.5">
                    <span className="text-[15px] font-bold">
                        {attribute.name}
                    </span>
                    {attribute.options.length > 0 ? (
                        <div className="flex flex-wrap gap-1.5">
                            {attribute.options.map((option) => (
                                <Chip key={option}>{option}</Chip>
                            ))}
                        </div>
                    ) : (
                        <span className="text-xs text-muted-foreground">
                            {attribute.type_label}
                        </span>
                    )}
                </div>
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => setEditing(true)}
                >
                    {t('action.edit')}
                </Button>
            </div>
        );
    }

    return (
        <Form
            {...updateAttribute.form(attribute.id)}
            options={{ preserveScroll: true }}
            onSuccess={() => setEditing(false)}
            className="flex flex-col gap-3 glass-inner p-3.5"
        >
            {({ processing, errors }) => (
                <>
                    <AttributeFields
                        categories={categories}
                        attribute={attribute}
                        errors={errors}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {t('action.save')}
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setEditing(false)}
                        >
                            {t('action.cancel')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

export default function Catalog({
    categories,
    attributes,
    products,
    suppliers,
}: Props) {
    return (
        <>
            <Head title={t('catalog.title')} />

            <div className="gap-4 xl:columns-2 [&>*]:mb-4 [&>*]:break-inside-avoid">
                <Panel
                    title={t('catalog.categories')}
                    description={t('catalog.categories.description')}
                >
                    <div className="flex flex-col gap-2">
                        {categories.map((category) => (
                            <div
                                key={category.id}
                                className="flex flex-wrap items-center justify-between gap-2 glass-inner px-3.5 py-2.5"
                            >
                                <span className="text-[15px] font-bold">
                                    {category.name}
                                </span>
                                <Chip
                                    tone={
                                        category.tracking === 'serial'
                                            ? 'info'
                                            : 'neutral'
                                    }
                                >
                                    {category.tracking_label}
                                </Chip>
                            </div>
                        ))}
                    </div>
                    <Form
                        {...storeCategory.form()}
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        className="flex flex-wrap items-end gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    label={t('catalog.category.name')}
                                    name="name"
                                    id="category-name"
                                    required
                                    placeholder="Telefon"
                                    wrapperClassName="flex-1 basis-40"
                                    error={errors.name}
                                />
                                <SelectField
                                    label={t('catalog.category.tracking')}
                                    name="tracking"
                                    id="category-tracking"
                                    wrapperClassName="flex-1 basis-40"
                                    error={errors.tracking}
                                >
                                    <option value="serial">
                                        {t('catalog.tracking.serial')}
                                    </option>
                                    <option value="quantity">
                                        {t('catalog.tracking.quantity')}
                                    </option>
                                </SelectField>
                                <Button type="submit" disabled={processing}>
                                    {t('action.add')}
                                </Button>
                            </>
                        )}
                    </Form>
                </Panel>

                <Panel
                    title={t('catalog.products')}
                    description={t('catalog.products.description')}
                >
                    <div className="flex flex-col gap-2">
                        {products.map((product) => (
                            <div
                                key={product.id}
                                className="flex flex-wrap items-center justify-between gap-2 glass-inner px-3.5 py-2.5"
                            >
                                <div className="flex min-w-0 flex-col">
                                    <span className="text-[15px] font-bold">
                                        {product.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {t('catalog.product.summary', {
                                            category: product.category,
                                            months: product.default_warranty_months,
                                            discount:
                                                product.default_max_discount_pct,
                                        })}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                    {categories.length === 0 ? (
                        <p className="text-sm text-ink-mid">
                            {t('catalog.products.need_category')}
                        </p>
                    ) : (
                        <Form
                            {...storeProduct.form()}
                            resetOnSuccess
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <Field
                                            label={t('catalog.product.name')}
                                            name="name"
                                            id="product-name"
                                            required
                                            placeholder="Samsung Galaxy A55"
                                            error={errors.name}
                                        />
                                        <SelectField
                                            label={t(
                                                'catalog.product.category',
                                            )}
                                            name="category_id"
                                            id="product-category"
                                            error={errors.category_id}
                                        >
                                            {categories.map((category) => (
                                                <option
                                                    key={category.id}
                                                    value={category.id}
                                                >
                                                    {category.name}
                                                </option>
                                            ))}
                                        </SelectField>
                                        <Field
                                            label={t(
                                                'catalog.product.warranty',
                                            )}
                                            name="default_warranty_months"
                                            id="product-warranty"
                                            inputMode="numeric"
                                            required
                                            defaultValue="12"
                                            className="num"
                                            error={
                                                errors.default_warranty_months
                                            }
                                        />
                                        <Field
                                            label={t(
                                                'catalog.product.discount',
                                            )}
                                            name="default_max_discount_pct"
                                            id="product-discount"
                                            inputMode="decimal"
                                            required
                                            defaultValue="0"
                                            className="num"
                                            error={
                                                errors.default_max_discount_pct
                                            }
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        className="self-start"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        {t('catalog.product.create')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </Panel>

                <Panel
                    title={t('catalog.attributes')}
                    description={t('catalog.attributes.description')}
                >
                    <div className="flex flex-col gap-2">
                        {attributes.map((attribute) => (
                            <AttributeRow
                                key={attribute.id}
                                attribute={attribute}
                                categories={categories}
                            />
                        ))}
                    </div>
                    {categories.length > 0 && (
                        <Form
                            {...storeAttribute.form()}
                            resetOnSuccess
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <AttributeFields
                                        categories={categories}
                                        errors={errors}
                                    />
                                    <Button
                                        type="submit"
                                        className="self-start"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        {t('catalog.attribute.create')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </Panel>

                <Panel
                    title={t('catalog.suppliers')}
                    description={t('catalog.suppliers.description')}
                >
                    <div className="flex flex-col gap-2">
                        {suppliers.map((supplier) => (
                            <div
                                key={supplier.id}
                                className="flex flex-col glass-inner px-3.5 py-2.5"
                            >
                                <span className="text-[15px] font-bold">
                                    {supplier.name}
                                </span>
                                <span className="text-xs num text-muted-foreground">
                                    {[supplier.phone, supplier.note]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                            </div>
                        ))}
                    </div>
                    <Form
                        {...storeSupplier.form()}
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        className="flex flex-col gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <Field
                                        label={t('catalog.supplier.name')}
                                        name="name"
                                        id="supplier-name"
                                        required
                                        error={errors.name}
                                    />
                                    <Field
                                        label={t('field.phone')}
                                        name="phone"
                                        id="supplier-phone"
                                        type="tel"
                                        inputMode="tel"
                                        placeholder="+998"
                                        error={errors.phone}
                                    />
                                </div>
                                <Field
                                    label={t('field.note')}
                                    name="note"
                                    id="supplier-note"
                                    error={errors.note}
                                />
                                <Button
                                    type="submit"
                                    className="self-start"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {t('catalog.supplier.create')}
                                </Button>
                            </>
                        )}
                    </Form>
                </Panel>
            </div>
        </>
    );
}

Catalog.layout = {
    breadcrumbs: [{ title: t('catalog.title'), href: index() }],
};
